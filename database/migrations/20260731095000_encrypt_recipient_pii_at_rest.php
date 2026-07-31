<?php

declare(strict_types=1);

use Hapa\Core\Security\PiiKeyProvider;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class EncryptRecipientPiiAtRest extends AbstractMigration
{
    public function up(): void
    {
        $connection = $this->getAdapter()->getConnection();
        if (!$connection instanceof \PDO) {
            throw new \RuntimeException('Connessione PDO necessaria per la migrazione PII.');
        }
        $statement = $connection->prepare(<<<'SQL'
SELECT set_config('hapa.pii_key', :pii_key, false),
       set_config('hapa.pii_key_id', :pii_key_id, false)
SQL);
        $statement->execute([
            'pii_key' => PiiKeyProvider::passphrase(),
            'pii_key_id' => PiiKeyProvider::keyId(),
        ]);

        $this->execute(<<<'SQL'
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE OR REPLACE FUNCTION hapa_require_pii_key()
RETURNS TEXT LANGUAGE plpgsql STABLE AS $$
DECLARE encryption_key TEXT;
BEGIN
    encryption_key := current_setting('hapa.pii_key', true);
    IF encryption_key IS NULL OR length(encryption_key) < 43 THEN
        RAISE EXCEPTION 'Chiave PII HAPA non configurata per la sessione PostgreSQL';
    END IF;
    RETURN encryption_key;
END
$$;

CREATE OR REPLACE FUNCTION hapa_pii_encrypt(value TEXT)
RETURNS TEXT LANGUAGE plpgsql VOLATILE STRICT AS $$
BEGIN
    IF value LIKE 'hapa:v1:%' THEN RETURN value; END IF;
    RETURN 'hapa:v1:' || replace(encode(
        pgp_sym_encrypt(value, hapa_require_pii_key(),
            'cipher-algo=aes256,compress-algo=1,s2k-mode=3'),
        'base64'
    ), E'\n', '');
END
$$;

CREATE OR REPLACE FUNCTION hapa_pii_decrypt(value TEXT)
RETURNS TEXT LANGUAGE plpgsql STABLE STRICT AS $$
BEGIN
    IF value NOT LIKE 'hapa:v1:%' THEN RETURN value; END IF;
    RETURN pgp_sym_decrypt(decode(substr(value, 9), 'base64'), hapa_require_pii_key());
END
$$;

CREATE OR REPLACE FUNCTION hapa_pii_blind_index(value TEXT)
RETURNS TEXT LANGUAGE sql STABLE STRICT AS $$
    SELECT encode(hmac(lower(btrim(value)), hapa_require_pii_key(), 'sha256'), 'hex')
$$;

CREATE OR REPLACE FUNCTION hapa_pii_encrypt_json(value JSONB)
RETURNS JSONB LANGUAGE plpgsql VOLATILE STRICT AS $$
BEGIN
    IF value ? '_hapa_pii' THEN RETURN value; END IF;
    RETURN jsonb_build_object(
        '_hapa_pii', hapa_pii_encrypt(value::TEXT),
        '_key_id', current_setting('hapa.pii_key_id', true)
    );
END
$$;

CREATE OR REPLACE FUNCTION hapa_pii_decrypt_json(value JSONB)
RETURNS JSONB LANGUAGE plpgsql STABLE STRICT AS $$
BEGIN
    IF NOT (value ? '_hapa_pii') THEN RETURN value; END IF;
    RETURN hapa_pii_decrypt(value ->> '_hapa_pii')::JSONB;
END
$$;

DROP INDEX IF EXISTS customers_display_name_idx;
ALTER TABLE customers
    DROP CONSTRAINT IF EXISTS customers_display_name_check,
    DROP CONSTRAINT IF EXISTS customers_optional_names_check,
    DROP CONSTRAINT IF EXISTS customers_business_name_check,
    DROP CONSTRAINT IF EXISTS customers_email_check,
    DROP CONSTRAINT IF EXISTS customers_optional_contact_check,
    ALTER COLUMN display_name TYPE TEXT USING display_name::TEXT,
    ALTER COLUMN first_name TYPE TEXT USING first_name::TEXT,
    ALTER COLUMN last_name TYPE TEXT USING last_name::TEXT,
    ALTER COLUMN company_name TYPE TEXT USING company_name::TEXT,
    ALTER COLUMN email TYPE TEXT USING email::TEXT,
    ALTER COLUMN phone TYPE TEXT USING phone::TEXT,
    ALTER COLUMN tax_identifier TYPE TEXT USING tax_identifier::TEXT,
    ALTER COLUMN vat_number TYPE TEXT USING vat_number::TEXT;

ALTER TABLE customer_addresses
    DROP CONSTRAINT IF EXISTS customer_addresses_required_values_check,
    DROP CONSTRAINT IF EXISTS customer_addresses_optional_values_check,
    ALTER COLUMN label TYPE TEXT USING label::TEXT,
    ALTER COLUMN recipient TYPE TEXT USING recipient::TEXT,
    ALTER COLUMN address_line1 TYPE TEXT USING address_line1::TEXT,
    ALTER COLUMN address_line2 TYPE TEXT USING address_line2::TEXT,
    ALTER COLUMN postal_code TYPE TEXT USING postal_code::TEXT,
    ALTER COLUMN city TYPE TEXT USING city::TEXT,
    ALTER COLUMN province TYPE TEXT USING province::TEXT,
    ALTER COLUMN phone TYPE TEXT USING phone::TEXT;

CREATE OR REPLACE FUNCTION hapa_encrypt_customer_row()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE plain_email TEXT;
BEGIN
    NEW.display_name := hapa_pii_encrypt(NEW.display_name);
    NEW.first_name := CASE WHEN NEW.first_name IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.first_name) END;
    NEW.last_name := CASE WHEN NEW.last_name IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.last_name) END;
    NEW.company_name := CASE WHEN NEW.company_name IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.company_name) END;
    NEW.phone := CASE WHEN NEW.phone IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.phone) END;
    NEW.tax_identifier := CASE WHEN NEW.tax_identifier IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.tax_identifier) END;
    NEW.vat_number := CASE WHEN NEW.vat_number IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.vat_number) END;
    IF NEW.email IS NULL THEN
        NEW.email_normalized := NULL;
    ELSE
        plain_email := hapa_pii_decrypt(NEW.email);
        NEW.email_normalized := hapa_pii_blind_index(plain_email);
        NEW.email := hapa_pii_encrypt(NEW.email);
    END IF;
    RETURN NEW;
END
$$;

CREATE OR REPLACE FUNCTION hapa_encrypt_customer_address_row()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.label := hapa_pii_encrypt(NEW.label);
    NEW.recipient := hapa_pii_encrypt(NEW.recipient);
    NEW.address_line1 := hapa_pii_encrypt(NEW.address_line1);
    NEW.address_line2 := CASE WHEN NEW.address_line2 IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.address_line2) END;
    NEW.postal_code := hapa_pii_encrypt(NEW.postal_code);
    NEW.city := hapa_pii_encrypt(NEW.city);
    NEW.province := CASE WHEN NEW.province IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.province) END;
    NEW.phone := CASE WHEN NEW.phone IS NULL THEN NULL ELSE hapa_pii_encrypt(NEW.phone) END;
    RETURN NEW;
END
$$;

CREATE OR REPLACE FUNCTION hapa_encrypt_order_address_row()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.shipping_address := CASE WHEN NEW.shipping_address IS NULL THEN NULL ELSE hapa_pii_encrypt_json(NEW.shipping_address) END;
    NEW.billing_address := CASE WHEN NEW.billing_address IS NULL THEN NULL ELSE hapa_pii_encrypt_json(NEW.billing_address) END;
    RETURN NEW;
END
$$;

CREATE OR REPLACE FUNCTION hapa_encrypt_json_payload_row()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.payload := hapa_pii_encrypt_json(NEW.payload);
    RETURN NEW;
END
$$;

CREATE OR REPLACE FUNCTION hapa_encrypt_customer_history_row()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.snapshot := hapa_pii_encrypt_json(NEW.snapshot);
    RETURN NEW;
END
$$;

DROP TRIGGER IF EXISTS customers_encrypt_pii ON customers;
CREATE TRIGGER customers_encrypt_pii
BEFORE INSERT OR UPDATE OF display_name, first_name, last_name, company_name,
    email, phone, tax_identifier, vat_number ON customers
FOR EACH ROW EXECUTE FUNCTION hapa_encrypt_customer_row();

DROP TRIGGER IF EXISTS customer_addresses_encrypt_pii ON customer_addresses;
CREATE TRIGGER customer_addresses_encrypt_pii
BEFORE INSERT OR UPDATE OF label, recipient, address_line1, address_line2,
    postal_code, city, province, phone ON customer_addresses
FOR EACH ROW EXECUTE FUNCTION hapa_encrypt_customer_address_row();

DROP TRIGGER IF EXISTS orders_encrypt_addresses ON orders;
CREATE TRIGGER orders_encrypt_addresses
BEFORE INSERT OR UPDATE OF shipping_address, billing_address ON orders
FOR EACH ROW EXECUTE FUNCTION hapa_encrypt_order_address_row();

DROP TRIGGER IF EXISTS customer_history_encrypt_snapshot ON customer_history;
CREATE TRIGGER customer_history_encrypt_snapshot
BEFORE INSERT OR UPDATE OF snapshot ON customer_history
FOR EACH ROW EXECUTE FUNCTION hapa_encrypt_customer_history_row();

DROP TRIGGER IF EXISTS inbox_messages_encrypt_payload ON inbox_messages;
CREATE TRIGGER inbox_messages_encrypt_payload
BEFORE INSERT OR UPDATE OF payload ON inbox_messages
FOR EACH ROW EXECUTE FUNCTION hapa_encrypt_json_payload_row();

DROP TRIGGER IF EXISTS outbox_messages_encrypt_payload ON outbox_messages;
CREATE TRIGGER outbox_messages_encrypt_payload
BEFORE INSERT OR UPDATE OF payload ON outbox_messages
FOR EACH ROW EXECUTE FUNCTION hapa_encrypt_json_payload_row();

UPDATE customers SET
    display_name = display_name, first_name = first_name, last_name = last_name,
    company_name = company_name, email = email, phone = phone,
    tax_identifier = tax_identifier, vat_number = vat_number;
UPDATE customer_addresses SET
    label = label, recipient = recipient, address_line1 = address_line1,
    address_line2 = address_line2, postal_code = postal_code, city = city,
    province = province, phone = phone;
UPDATE orders SET shipping_address = shipping_address, billing_address = billing_address;
UPDATE customer_history SET snapshot = snapshot;
UPDATE inbox_messages SET payload = payload;
UPDATE outbox_messages SET payload = payload;

ALTER TABLE customers
    ADD CONSTRAINT customers_display_name_check CHECK (display_name LIKE 'hapa:v1:%'),
    ADD CONSTRAINT customers_optional_names_check CHECK (
        (first_name IS NULL OR first_name LIKE 'hapa:v1:%')
        AND (last_name IS NULL OR last_name LIKE 'hapa:v1:%')
        AND (company_name IS NULL OR company_name LIKE 'hapa:v1:%')
    ),
    ADD CONSTRAINT customers_business_name_check CHECK (customer_type <> 'business' OR company_name IS NOT NULL),
    ADD CONSTRAINT customers_email_check CHECK (
        (email IS NULL AND email_normalized IS NULL)
        OR (email LIKE 'hapa:v1:%' AND email_normalized ~ '^[0-9a-f]{64}$')
    ),
    ADD CONSTRAINT customers_optional_contact_check CHECK (
        (phone IS NULL OR phone LIKE 'hapa:v1:%')
        AND (tax_identifier IS NULL OR tax_identifier LIKE 'hapa:v1:%')
        AND (vat_number IS NULL OR vat_number LIKE 'hapa:v1:%')
    );

ALTER TABLE customer_addresses
    ADD CONSTRAINT customer_addresses_required_values_check CHECK (
        label LIKE 'hapa:v1:%' AND recipient LIKE 'hapa:v1:%'
        AND address_line1 LIKE 'hapa:v1:%' AND postal_code LIKE 'hapa:v1:%'
        AND city LIKE 'hapa:v1:%'
    ),
    ADD CONSTRAINT customer_addresses_optional_values_check CHECK (
        (address_line2 IS NULL OR address_line2 LIKE 'hapa:v1:%')
        AND (province IS NULL OR province LIKE 'hapa:v1:%')
        AND (phone IS NULL OR phone LIKE 'hapa:v1:%')
    );
SQL);
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'La cifratura PII a riposo non può essere rimossa automaticamente senza riportare dati personali in chiaro.',
        );
    }
}
