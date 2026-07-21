<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeparateBusinessSubdomains extends AbstractMigration
{
    public function up(): void
    {
        $this->migratePart1();
        $this->migratePart2();
        $this->migratePart3();
    }


    private function migratePart1(): void
    {
        $this->migratePart1A();
        $this->migratePart1B();
    }

    private function migratePart1A(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE marketplaces
    ADD COLUMN business_status VARCHAR(24) NOT NULL DEFAULT 'planned',
    ADD CONSTRAINT marketplaces_business_status_check
        CHECK (business_status IN ('active', 'pilot', 'planned', 'retired'))
SQL);
        $this->execute("UPDATE marketplaces SET business_status = 'active' WHERE lower(code) = 'ibs'");

        $this->execute(<<<'SQL'
INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
VALUES
    ('ibs', 'IBS', 'ibs', FALSE, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('temu', 'Temu', 'temu', FALSE, 'planned', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('amazon', 'Amazon', 'amazon', FALSE, 'planned', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT (code) DO UPDATE
SET name = EXCLUDED.name,
    business_status = EXCLUDED.business_status,
    updated_at = CURRENT_TIMESTAMP
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE marketplace_accounts (
    id BIGSERIAL PRIMARY KEY,
    marketplace_id INTEGER NOT NULL,
    code VARCHAR(96) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    seller_reference VARCHAR(160) NULL,
    connector_code VARCHAR(96) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'disabled',
    technical_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    version INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT marketplace_accounts_marketplace_fk
        FOREIGN KEY (marketplace_id) REFERENCES marketplaces (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT marketplace_accounts_code_unique UNIQUE (code),
    CONSTRAINT marketplace_accounts_status_check
        CHECK (status IN ('disabled', 'pilot', 'active', 'suspended', 'retired')),
    CONSTRAINT marketplace_accounts_values_check CHECK (
        btrim(code) <> ''
        AND btrim(display_name) <> ''
        AND btrim(connector_code) <> ''
        AND (seller_reference IS NULL OR btrim(seller_reference) <> '')
        AND version > 0
    )
)
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX marketplace_accounts_seller_unique
    ON marketplace_accounts (marketplace_id, seller_reference)
    WHERE seller_reference IS NOT NULL
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE orders
    ADD COLUMN marketplace_account_id BIGINT NULL,
    ADD COLUMN subtotal_minor BIGINT NULL,
    ADD COLUMN shipping_total_minor BIGINT NULL,
    ADD COLUMN discount_total_minor BIGINT NULL,
    ADD COLUMN tax_total_minor BIGINT NULL,
    ADD COLUMN grand_total_minor BIGINT NULL,
    ADD CONSTRAINT orders_marketplace_account_fk
        FOREIGN KEY (marketplace_account_id) REFERENCES marketplace_accounts (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT orders_totals_check CHECK (
        (subtotal_minor IS NULL OR subtotal_minor >= 0)
        AND (shipping_total_minor IS NULL OR shipping_total_minor >= 0)
        AND (discount_total_minor IS NULL OR discount_total_minor >= 0)
        AND (tax_total_minor IS NULL OR tax_total_minor >= 0)
        AND (grand_total_minor IS NULL OR grand_total_minor >= 0)
    )
SQL);
        $this->execute('CREATE INDEX orders_marketplace_account_idx ON orders (marketplace_account_id, created_at DESC) WHERE marketplace_account_id IS NOT NULL');

        $this->execute(<<<'SQL'
ALTER TABLE customers
    ADD COLUMN version INTEGER NOT NULL DEFAULT 1,
    ADD CONSTRAINT customers_version_check CHECK (version > 0)
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE customer_history (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL,
    version INTEGER NOT NULL,
    change_type VARCHAR(64) NOT NULL,
    snapshot JSONB NOT NULL,
    actor_id VARCHAR(160) NULL,
    correlation_id VARCHAR(200) NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT customer_history_customer_fk
        FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT customer_history_version_unique UNIQUE (customer_id, version),
    CONSTRAINT customer_history_values_check CHECK (
        version > 0
        AND btrim(change_type) <> ''
        AND jsonb_typeof(snapshot) = 'object'
    )
)
SQL);
    }

    private function migratePart1B(): void
    {
        $this->execute('CREATE INDEX customer_history_timeline_idx ON customer_history (customer_id, occurred_at DESC, id DESC)');

        $this->execute(<<<'SQL'
CREATE TABLE suppliers (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(160) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT suppliers_code_unique UNIQUE (code),
    CONSTRAINT suppliers_values_check CHECK (btrim(code) <> '' AND btrim(name) <> '')
)
SQL);
        $this->execute(<<<'SQL'
INSERT INTO suppliers (code, name, active)
VALUES ('space', 'Space', TRUE)
ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, active = TRUE, updated_at = CURRENT_TIMESTAMP
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE supplier_catalog_items (
    id BIGSERIAL PRIMARY KEY,
    supplier_id BIGINT NOT NULL,
    catalog_item_id BIGINT NOT NULL,
    external_item_id VARCHAR(160) NULL,
    supplier_sku VARCHAR(160) NULL,
    purchase_cost_minor BIGINT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    available_quantity INTEGER NOT NULL DEFAULT 0,
    source_version VARCHAR(200) NULL,
    observed_at TIMESTAMPTZ NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT supplier_catalog_items_supplier_fk
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT supplier_catalog_items_catalog_fk
        FOREIGN KEY (catalog_item_id) REFERENCES catalog_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT supplier_catalog_items_product_unique UNIQUE (supplier_id, catalog_item_id),
    CONSTRAINT supplier_catalog_items_values_check CHECK (
        (external_item_id IS NULL OR btrim(external_item_id) <> '')
        AND (supplier_sku IS NULL OR btrim(supplier_sku) <> '')
        AND (source_version IS NULL OR btrim(source_version) <> '')
        AND (purchase_cost_minor IS NULL OR purchase_cost_minor >= 0)
        AND available_quantity >= 0
        AND currency ~ '^[A-Z]{3}$'
    )
)
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX supplier_catalog_items_external_unique
    ON supplier_catalog_items (supplier_id, external_item_id)
    WHERE external_item_id IS NOT NULL
SQL);
        $this->execute(<<<'SQL'
INSERT INTO supplier_catalog_items (
    supplier_id, catalog_item_id, external_item_id, supplier_sku,
    purchase_cost_minor, currency, available_quantity, source_version,
    observed_at, active, created_at, updated_at
)
SELECT supplier.id, item.id, item.space_item_id, item.sku,
       item.space_price_minor, item.currency, item.space_available_quantity,
       item.source_version, item.last_space_sync_at, item.active,
       item.created_at, item.updated_at
FROM catalog_items AS item
JOIN suppliers AS supplier ON supplier.code = 'space'
WHERE item.space_item_id IS NOT NULL
   OR item.space_price_minor IS NOT NULL
   OR item.source_version IS NOT NULL
ON CONFLICT (supplier_id, catalog_item_id) DO NOTHING
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE order_lines
    ADD COLUMN catalog_item_id BIGINT NULL,
    ADD COLUMN description_snapshot VARCHAR(500) NULL,
    ADD COLUMN unit_price_minor BIGINT NULL,
    ADD COLUMN tax_rate_basis_points INTEGER NULL,
    ADD COLUMN discount_total_minor BIGINT NOT NULL DEFAULT 0,
    ADD COLUMN line_total_minor BIGINT NULL,
    ADD CONSTRAINT order_lines_catalog_item_fk
        FOREIGN KEY (catalog_item_id) REFERENCES catalog_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT order_lines_financials_check CHECK (
        (description_snapshot IS NULL OR btrim(description_snapshot) <> '')
        AND (unit_price_minor IS NULL OR unit_price_minor >= 0)
        AND (tax_rate_basis_points IS NULL OR tax_rate_basis_points BETWEEN 0 AND 10000)
        AND discount_total_minor >= 0
        AND (line_total_minor IS NULL OR line_total_minor >= 0)
    )
SQL);
    }

    private function migratePart2(): void
    {
        $this->execute('CREATE INDEX order_lines_catalog_item_idx ON order_lines (catalog_item_id) WHERE catalog_item_id IS NOT NULL');

        $this->execute(<<<'SQL'
CREATE TABLE supplier_purchase_orders (
    id BIGSERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    supplier_id BIGINT NOT NULL,
    purchase_number VARCHAR(64) NOT NULL,
    external_purchase_id VARCHAR(200) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    subtotal_minor BIGINT NULL,
    tax_total_minor BIGINT NULL,
    grand_total_minor BIGINT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    submitted_at TIMESTAMPTZ NULL,
    accepted_at TIMESTAMPTZ NULL,
    completed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT supplier_purchase_orders_order_fk
        FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT supplier_purchase_orders_supplier_fk
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT supplier_purchase_orders_number_unique UNIQUE (purchase_number),
    CONSTRAINT supplier_purchase_orders_status_check CHECK (
        status IN ('draft', 'requested', 'accepted', 'partially_available', 'ready', 'completed', 'rejected', 'cancelled', 'manual_review')
    ),
    CONSTRAINT supplier_purchase_orders_values_check CHECK (
        btrim(purchase_number) <> ''
        AND (external_purchase_id IS NULL OR btrim(external_purchase_id) <> '')
        AND currency ~ '^[A-Z]{3}$'
        AND (subtotal_minor IS NULL OR subtotal_minor >= 0)
        AND (tax_total_minor IS NULL OR tax_total_minor >= 0)
        AND (grand_total_minor IS NULL OR grand_total_minor >= 0)
        AND version > 0
    )
)
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX supplier_purchase_orders_external_unique
    ON supplier_purchase_orders (supplier_id, external_purchase_id)
    WHERE external_purchase_id IS NOT NULL
SQL);
        $this->execute('CREATE INDEX supplier_purchase_orders_order_idx ON supplier_purchase_orders (order_id, created_at DESC)');

        $this->execute(<<<'SQL'
CREATE TABLE supplier_purchase_order_lines (
    id BIGSERIAL PRIMARY KEY,
    purchase_order_id BIGINT NOT NULL,
    order_line_id INTEGER NULL,
    supplier_catalog_item_id BIGINT NULL,
    line_number INTEGER NOT NULL,
    supplier_sku VARCHAR(160) NOT NULL,
    description_snapshot VARCHAR(500) NULL,
    quantity INTEGER NOT NULL,
    unit_cost_minor BIGINT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT supplier_purchase_order_lines_order_fk
        FOREIGN KEY (purchase_order_id) REFERENCES supplier_purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT supplier_purchase_order_lines_sales_line_fk
        FOREIGN KEY (order_line_id) REFERENCES order_lines (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT supplier_purchase_order_lines_catalog_fk
        FOREIGN KEY (supplier_catalog_item_id) REFERENCES supplier_catalog_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT supplier_purchase_order_lines_number_unique UNIQUE (purchase_order_id, line_number),
    CONSTRAINT supplier_purchase_order_lines_values_check CHECK (
        line_number > 0
        AND btrim(supplier_sku) <> ''
        AND (description_snapshot IS NULL OR btrim(description_snapshot) <> '')
        AND quantity > 0
        AND (unit_cost_minor IS NULL OR unit_cost_minor >= 0)
        AND currency ~ '^[A-Z]{3}$'
    )
)
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE shipment_packages (
    id BIGSERIAL PRIMARY KEY,
    shipment_id INTEGER NOT NULL,
    package_number INTEGER NOT NULL,
    weight_grams INTEGER NOT NULL,
    length_mm INTEGER NULL,
    width_mm INTEGER NULL,
    height_mm INTEGER NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT shipment_packages_shipment_fk
        FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT shipment_packages_number_unique UNIQUE (shipment_id, package_number),
    CONSTRAINT shipment_packages_values_check CHECK (
        package_number > 0
        AND weight_grams > 0
        AND (length_mm IS NULL OR length_mm > 0)
        AND (width_mm IS NULL OR width_mm > 0)
        AND (height_mm IS NULL OR height_mm > 0)
    )
)
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE shipment_labels (
    id BIGSERIAL PRIMARY KEY,
    shipment_id INTEGER NOT NULL,
    format VARCHAR(24) NOT NULL,
    storage_reference VARCHAR(500) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    generated_at TIMESTAMPTZ NOT NULL,
    expires_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT shipment_labels_shipment_fk
        FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT shipment_labels_values_check CHECK (
        format IN ('PDF', 'ZPL', 'PNG')
        AND btrim(storage_reference) <> ''
        AND checksum_sha256 ~ '^[0-9a-f]{64}$'
        AND (expires_at IS NULL OR expires_at > generated_at)
    )
)
SQL);
        $this->execute('CREATE INDEX shipment_labels_shipment_idx ON shipment_labels (shipment_id, generated_at DESC)');

        $this->execute(<<<'SQL'
ALTER TABLE marketplace_offers
    ADD COLUMN marketplace_account_id BIGINT NULL,
    ADD CONSTRAINT marketplace_offers_account_fk
        FOREIGN KEY (marketplace_account_id) REFERENCES marketplace_accounts (id) ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
        $this->execute('ALTER TABLE marketplace_offers DROP CONSTRAINT marketplace_offers_item_marketplace_unique');
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX marketplace_offers_item_account_unique
    ON marketplace_offers (catalog_item_id, marketplace_account_id)
    WHERE marketplace_account_id IS NOT NULL
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX marketplace_offers_legacy_item_marketplace_unique
    ON marketplace_offers (catalog_item_id, marketplace_id)
    WHERE marketplace_account_id IS NULL
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE outbox_messages
    ADD COLUMN exchange_name VARCHAR(160) NOT NULL DEFAULT 'hapa.events',
    ADD COLUMN routing_key VARCHAR(200) NULL,
    ADD CONSTRAINT outbox_exchange_name_check
        CHECK (exchange_name IN ('hapa.events', 'hapa.commands'))
SQL);
        $this->execute('UPDATE outbox_messages SET routing_key = event_type WHERE routing_key IS NULL');
        $this->execute('ALTER TABLE outbox_messages ALTER COLUMN routing_key SET NOT NULL');
        $this->execute("ALTER TABLE outbox_messages ADD CONSTRAINT outbox_routing_key_check CHECK (btrim(routing_key) <> '')");

    }

    private function migratePart3(): void
    {
        $this->execute("COMMENT ON COLUMN catalog_items.space_price_minor IS 'Legacy: usare supplier_catalog_items.purchase_cost_minor'");
        $this->execute("COMMENT ON COLUMN catalog_items.space_available_quantity IS 'Legacy: usare supplier_catalog_items.available_quantity'");
        $this->execute('ALTER TABLE external_deliveries RENAME TO legacy_external_deliveries');
        $this->execute("COMMENT ON TABLE legacy_external_deliveries IS 'Sola conservazione legacy: nuove operazioni provider appartengono a hapa-automation.provider_operations'");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE legacy_external_deliveries RENAME TO external_deliveries');

        $this->execute('ALTER TABLE outbox_messages DROP CONSTRAINT IF EXISTS outbox_routing_key_check');
        $this->execute('ALTER TABLE outbox_messages DROP CONSTRAINT IF EXISTS outbox_exchange_name_check');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS routing_key');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS exchange_name');

        $this->execute('DROP INDEX IF EXISTS marketplace_offers_legacy_item_marketplace_unique');
        $this->execute('DROP INDEX IF EXISTS marketplace_offers_item_account_unique');
        $this->execute('ALTER TABLE marketplace_offers DROP CONSTRAINT IF EXISTS marketplace_offers_account_fk');
        $this->execute('ALTER TABLE marketplace_offers DROP COLUMN IF EXISTS marketplace_account_id');
        $this->execute('ALTER TABLE marketplace_offers ADD CONSTRAINT marketplace_offers_item_marketplace_unique UNIQUE (catalog_item_id, marketplace_id)');

        $this->execute('DROP TABLE IF EXISTS shipment_labels');
        $this->execute('DROP TABLE IF EXISTS shipment_packages');
        $this->execute('DROP TABLE IF EXISTS supplier_purchase_order_lines');
        $this->execute('DROP TABLE IF EXISTS supplier_purchase_orders');

        $this->execute('ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_financials_check');
        $this->execute('ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_catalog_item_fk');
        $this->execute(<<<'SQL'
ALTER TABLE order_lines
    DROP COLUMN IF EXISTS line_total_minor,
    DROP COLUMN IF EXISTS discount_total_minor,
    DROP COLUMN IF EXISTS tax_rate_basis_points,
    DROP COLUMN IF EXISTS unit_price_minor,
    DROP COLUMN IF EXISTS description_snapshot,
    DROP COLUMN IF EXISTS catalog_item_id
SQL);

        $this->execute('DROP TABLE IF EXISTS supplier_catalog_items');
        $this->execute("DELETE FROM suppliers WHERE code = 'space'");
        $this->execute('DROP TABLE IF EXISTS suppliers');
        $this->execute('DROP TABLE IF EXISTS customer_history');
        $this->execute('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_version_check');
        $this->execute('ALTER TABLE customers DROP COLUMN IF EXISTS version');

        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_totals_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_marketplace_account_fk');
        $this->execute(<<<'SQL'
ALTER TABLE orders
    DROP COLUMN IF EXISTS grand_total_minor,
    DROP COLUMN IF EXISTS tax_total_minor,
    DROP COLUMN IF EXISTS discount_total_minor,
    DROP COLUMN IF EXISTS shipping_total_minor,
    DROP COLUMN IF EXISTS subtotal_minor,
    DROP COLUMN IF EXISTS marketplace_account_id
SQL);
        $this->execute('DROP TABLE IF EXISTS marketplace_accounts');
        $this->execute('ALTER TABLE marketplaces DROP CONSTRAINT IF EXISTS marketplaces_business_status_check');
        $this->execute('ALTER TABLE marketplaces DROP COLUMN IF EXISTS business_status');
    }
}
