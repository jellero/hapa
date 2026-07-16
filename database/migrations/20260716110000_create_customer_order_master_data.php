<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCustomerOrderMasterData extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE customers (
    id BIGSERIAL PRIMARY KEY,
    customer_code VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    customer_type VARCHAR(32) NOT NULL DEFAULT 'person',
    display_name VARCHAR(240) NOT NULL,
    first_name VARCHAR(120) NULL,
    last_name VARCHAR(120) NULL,
    company_name VARCHAR(240) NULL,
    email VARCHAR(254) NULL,
    email_normalized VARCHAR(254) NULL,
    phone VARCHAR(64) NULL,
    tax_identifier VARCHAR(64) NULL,
    vat_number VARCHAR(32) NULL,
    locale VARCHAR(16) NOT NULL DEFAULT 'it-IT',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT customers_code_unique UNIQUE (customer_code),
    CONSTRAINT customers_code_check CHECK (customer_code ~ '^[A-Z0-9][A-Z0-9._-]{2,63}$'),
    CONSTRAINT customers_status_check CHECK (status IN ('active', 'inactive', 'archived')),
    CONSTRAINT customers_type_check CHECK (customer_type IN ('person', 'business')),
    CONSTRAINT customers_display_name_check CHECK (btrim(display_name) <> ''),
    CONSTRAINT customers_optional_names_check CHECK (
        (first_name IS NULL OR btrim(first_name) <> '')
        AND (last_name IS NULL OR btrim(last_name) <> '')
        AND (company_name IS NULL OR btrim(company_name) <> '')
    ),
    CONSTRAINT customers_business_name_check CHECK (
        customer_type <> 'business' OR company_name IS NOT NULL
    ),
    CONSTRAINT customers_email_check CHECK (
        (email IS NULL AND email_normalized IS NULL)
        OR (
            email IS NOT NULL
            AND email_normalized IS NOT NULL
            AND btrim(email) <> ''
            AND email_normalized = lower(btrim(email))
        )
    ),
    CONSTRAINT customers_optional_contact_check CHECK (
        (phone IS NULL OR btrim(phone) <> '')
        AND (tax_identifier IS NULL OR btrim(tax_identifier) <> '')
        AND (vat_number IS NULL OR btrim(vat_number) <> '')
    ),
    CONSTRAINT customers_locale_check CHECK (locale ~ '^[a-z]{2,3}(-[A-Z]{2})?$')
)
SQL);

        $this->execute('CREATE INDEX customers_email_normalized_idx ON customers (email_normalized) WHERE email_normalized IS NOT NULL');
        $this->execute('CREATE INDEX customers_display_name_idx ON customers (lower(display_name))');

        $this->execute(<<<'SQL'
CREATE TABLE customer_external_identities (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    source VARCHAR(32) NOT NULL,
    account_reference VARCHAR(160) NOT NULL,
    external_customer_id VARCHAR(160) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT customer_external_identities_customer_fk
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT customer_external_identities_source_check
        CHECK (source IN ('amazon', 'emag', 'temu', 'ibs', 'b2c_ecommerce')),
    CONSTRAINT customer_external_identities_values_check CHECK (
        btrim(account_reference) <> '' AND btrim(external_customer_id) <> ''
    ),
    CONSTRAINT customer_external_identities_unique
        UNIQUE (source, account_reference, external_customer_id)
)
SQL);

        $this->execute('CREATE INDEX customer_external_identities_customer_idx ON customer_external_identities (customer_id)');

        $this->execute(<<<'SQL'
CREATE TABLE customer_addresses (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    label VARCHAR(80) NOT NULL,
    recipient VARCHAR(240) NOT NULL,
    address_line1 VARCHAR(240) NOT NULL,
    address_line2 VARCHAR(240) NULL,
    postal_code VARCHAR(32) NOT NULL,
    city VARCHAR(160) NOT NULL,
    province VARCHAR(120) NULL,
    country_code CHAR(2) NOT NULL,
    phone VARCHAR(64) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    is_default_shipping BOOLEAN NOT NULL DEFAULT FALSE,
    is_default_billing BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT customer_addresses_customer_fk
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT customer_addresses_required_values_check CHECK (
        btrim(label) <> ''
        AND btrim(recipient) <> ''
        AND btrim(address_line1) <> ''
        AND btrim(postal_code) <> ''
        AND btrim(city) <> ''
    ),
    CONSTRAINT customer_addresses_optional_values_check CHECK (
        (address_line2 IS NULL OR btrim(address_line2) <> '')
        AND (province IS NULL OR btrim(province) <> '')
        AND (phone IS NULL OR btrim(phone) <> '')
    ),
    CONSTRAINT customer_addresses_country_check CHECK (country_code ~ '^[A-Z]{2}$'),
    CONSTRAINT customer_addresses_defaults_check CHECK (
        active OR (NOT is_default_shipping AND NOT is_default_billing)
    )
)
SQL);

        $this->execute('CREATE INDEX customer_addresses_customer_idx ON customer_addresses (customer_id, active)');
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX customer_addresses_default_shipping_unique
    ON customer_addresses (customer_id)
    WHERE is_default_shipping
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX customer_addresses_default_billing_unique
    ON customer_addresses (customer_id)
    WHERE is_default_billing
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE orders
    ADD COLUMN customer_id BIGINT NULL,
    ADD COLUMN order_number VARCHAR(64) NULL,
    ADD COLUMN origin VARCHAR(32) NOT NULL DEFAULT 'marketplace',
    ADD COLUMN origin_reference VARCHAR(160) NULL,
    ADD COLUMN billing_address JSONB NULL,
    ADD COLUMN placed_at TIMESTAMPTZ NULL,
    ALTER COLUMN marketplace_id DROP NOT NULL
SQL);

        $this->execute("UPDATE orders SET order_number = 'HAPA-' || lpad(id::text, 10, '0') WHERE order_number IS NULL");

        $this->execute(<<<'SQL'
ALTER TABLE orders
    ALTER COLUMN order_number SET NOT NULL,
    ADD CONSTRAINT orders_customer_fk
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT orders_order_number_unique UNIQUE (order_number),
    ADD CONSTRAINT orders_order_number_check CHECK (order_number ~ '^[A-Z0-9][A-Z0-9._-]{2,63}$'),
    ADD CONSTRAINT orders_external_order_id_check CHECK (btrim(external_order_id) <> ''),
    ADD CONSTRAINT orders_origin_check CHECK (
        (
            origin = 'marketplace'
            AND marketplace_id IS NOT NULL
            AND origin_reference IS NULL
        )
        OR (
            origin = 'b2c_ecommerce'
            AND marketplace_id IS NULL
            AND origin_reference IS NOT NULL
            AND btrim(origin_reference) <> ''
        )
    ),
    ADD CONSTRAINT orders_address_snapshots_check CHECK (
        (shipping_address IS NULL OR jsonb_typeof(shipping_address) = 'object')
        AND (billing_address IS NULL OR jsonb_typeof(billing_address) = 'object')
    )
SQL);

        $this->execute('CREATE INDEX orders_customer_placed_at_idx ON orders (customer_id, placed_at DESC NULLS LAST, created_at DESC) WHERE customer_id IS NOT NULL');
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX orders_b2c_external_unique
    ON orders (origin_reference, external_order_id)
    WHERE origin = 'b2c_ecommerce'
SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM orders WHERE marketplace_id IS NULL) THEN
        RAISE EXCEPTION 'Rollback impossibile: esistono ordini non associati a un marketplace';
    END IF;
END
$$
SQL);

        $this->execute('DROP INDEX IF EXISTS orders_b2c_external_unique');
        $this->execute('DROP INDEX IF EXISTS orders_customer_placed_at_idx');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_address_snapshots_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_origin_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_external_order_id_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_number_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_number_unique');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_customer_fk');
        $this->execute(<<<'SQL'
ALTER TABLE orders
    ALTER COLUMN marketplace_id SET NOT NULL,
    DROP COLUMN IF EXISTS placed_at,
    DROP COLUMN IF EXISTS billing_address,
    DROP COLUMN IF EXISTS origin_reference,
    DROP COLUMN IF EXISTS origin,
    DROP COLUMN IF EXISTS order_number,
    DROP COLUMN IF EXISTS customer_id
SQL);

        $this->execute('DROP TABLE IF EXISTS customer_addresses');
        $this->execute('DROP TABLE IF EXISTS customer_external_identities');
        $this->execute('DROP TABLE IF EXISTS customers');
    }
}
