<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IntegrateSpaceSuppliers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE space_suppliers (
    id BIGSERIAL PRIMARY KEY,
    space_supplier_id VARCHAR(64) NOT NULL UNIQUE,
    legal_name VARCHAR(255) NULL,
    code VARCHAR(100) NULL,
    currency VARCHAR(3) NULL,
    delivery_days INTEGER NULL,
    precision_score INTEGER NULL,
    closing_order VARCHAR(255) NULL,
    city VARCHAR(160) NULL,
    state_id VARCHAR(64) NULL,
    province VARCHAR(80) NULL,
    postal_code VARCHAR(32) NULL,
    address TEXT NULL,
    country VARCHAR(160) NULL,
    country_code VARCHAR(8) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    source_event_id VARCHAR(64) NULL,
    source_operation VARCHAR(16) NOT NULL DEFAULT 'upsert',
    source_observed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT space_suppliers_identity_check CHECK (BTRIM(space_supplier_id) <> ''),
    CONSTRAINT space_suppliers_currency_check CHECK (currency IS NULL OR currency ~ '^[A-Z]{3}$'),
    CONSTRAINT space_suppliers_delivery_check CHECK (delivery_days IS NULL OR delivery_days >= 0),
    CONSTRAINT space_suppliers_operation_check CHECK (source_operation IN ('upsert', 'delete'))
)
SQL);
        $this->execute('CREATE INDEX space_suppliers_name_idx ON space_suppliers (legal_name, space_supplier_id)');
        $this->execute('ALTER TABLE supplier_catalog_items ADD COLUMN space_supplier_id VARCHAR(64) NULL');
        $this->execute(<<<'SQL'
UPDATE supplier_catalog_items
SET space_supplier_id = COALESCE(
    NULLIF(BTRIM(source_attributes->>'id_fornitore'), ''),
    NULLIF(REGEXP_REPLACE(COALESCE(branch_suffix, ''), '^[Aa]', ''), '')
)
WHERE NULLIF(BTRIM(source_attributes->>'id_fornitore'), '') IS NOT NULL
   OR branch_suffix ~ '^[Aa]?[0-9]+$'
SQL);
        $this->execute(<<<'SQL'
INSERT INTO space_suppliers (space_supplier_id)
SELECT DISTINCT space_supplier_id
FROM supplier_catalog_items
WHERE space_supplier_id IS NOT NULL
ON CONFLICT (space_supplier_id) DO NOTHING
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE supplier_catalog_items
    ADD CONSTRAINT supplier_catalog_space_supplier_fk
    FOREIGN KEY (space_supplier_id) REFERENCES space_suppliers (space_supplier_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
SQL);
        $this->execute('CREATE INDEX supplier_catalog_items_space_supplier_idx ON supplier_catalog_items (space_supplier_id) WHERE space_supplier_id IS NOT NULL');
        $this->execute('ALTER TABLE catalog_publication_rules DROP CONSTRAINT catalog_publication_rules_field_check');
        $this->execute(<<<'SQL'
ALTER TABLE catalog_publication_rules
    ADD CONSTRAINT catalog_publication_rules_field_check CHECK (
        field IN ('sku', 'supplier_id', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity')
    )
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE catalog_publication_rules DROP CONSTRAINT IF EXISTS catalog_publication_rules_field_check');
        $this->execute(<<<'SQL'
ALTER TABLE catalog_publication_rules
    ADD CONSTRAINT catalog_publication_rules_field_check CHECK (
        field IN ('sku', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity')
    )
SQL);
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_space_supplier_idx');
        $this->execute('ALTER TABLE supplier_catalog_items DROP CONSTRAINT IF EXISTS supplier_catalog_space_supplier_fk');
        $this->execute('ALTER TABLE supplier_catalog_items DROP COLUMN IF EXISTS space_supplier_id');
        $this->execute('DROP TABLE IF EXISTS space_suppliers');
    }
}
