<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExtendSpaceFeedAndPublicationRules extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE supplier_catalog_items
    ADD COLUMN feed_name VARCHAR(80) NULL,
    ADD COLUMN artist VARCHAR(255) NULL,
    ADD COLUMN title VARCHAR(255) NULL,
    ADD COLUMN format VARCHAR(80) NULL,
    ADD COLUMN label VARCHAR(255) NULL,
    ADD COLUMN category VARCHAR(160) NULL,
    ADD COLUMN family VARCHAR(160) NULL,
    ADD COLUMN group_name VARCHAR(160) NULL,
    ADD COLUMN branch_suffix VARCHAR(40) NULL,
    ADD COLUMN delivery_time_days INTEGER NULL,
    ADD COLUMN source_status INTEGER NULL,
    ADD COLUMN precision_score INTEGER NULL,
    ADD COLUMN product_url VARCHAR(2048) NULL,
    ADD COLUMN image_url VARCHAR(2048) NULL,
    ADD COLUMN release_date DATE NULL,
    ADD COLUMN weight NUMERIC(12,3) NULL,
    ADD COLUMN weight_unit VARCHAR(16) NULL,
    ADD COLUMN missing_from_source BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN temu_sync_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN source_attributes JSONB NOT NULL DEFAULT '{}'::jsonb,
    ADD CONSTRAINT supplier_catalog_delivery_check CHECK (delivery_time_days IS NULL OR delivery_time_days >= 0),
    ADD CONSTRAINT supplier_catalog_attributes_check CHECK (jsonb_typeof(source_attributes) = 'object')
SQL);
        $this->execute('CREATE INDEX supplier_catalog_items_feed_idx ON supplier_catalog_items (feed_name, observed_at DESC)');
        $this->execute('CREATE INDEX supplier_catalog_items_branch_idx ON supplier_catalog_items (branch_suffix) WHERE branch_suffix IS NOT NULL');

        $this->execute(<<<'SQL'
CREATE TABLE catalog_publication_rules (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    marketplace_id BIGINT NULL,
    action VARCHAR(16) NOT NULL,
    field VARCHAR(40) NOT NULL,
    operator VARCHAR(24) NOT NULL,
    match_value VARCHAR(500) NOT NULL,
    priority INTEGER NOT NULL DEFAULT 100,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    version INTEGER NOT NULL DEFAULT 1,
    created_by VARCHAR(160) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    retired_at TIMESTAMPTZ NULL,
    CONSTRAINT catalog_publication_rules_marketplace_fk FOREIGN KEY (marketplace_id)
        REFERENCES marketplaces (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT catalog_publication_rules_action_check CHECK (action IN ('include', 'exclude')),
    CONSTRAINT catalog_publication_rules_field_check CHECK (
        field IN ('sku', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity')
    ),
    CONSTRAINT catalog_publication_rules_operator_check CHECK (
        operator IN ('equals', 'contains', 'starts_with', 'ends_with', 'minimum', 'maximum')
    ),
    CONSTRAINT catalog_publication_rules_values_check CHECK (
        btrim(code) <> '' AND btrim(name) <> '' AND btrim(match_value) <> '' AND priority >= 0 AND version > 0
    )
)
SQL);
        $this->execute('CREATE INDEX catalog_publication_rules_active_idx ON catalog_publication_rules (priority, id) WHERE enabled AND retired_at IS NULL');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS catalog_publication_rules');
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_branch_idx');
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_feed_idx');
        $this->execute(<<<'SQL'
ALTER TABLE supplier_catalog_items
    DROP CONSTRAINT IF EXISTS supplier_catalog_attributes_check,
    DROP CONSTRAINT IF EXISTS supplier_catalog_delivery_check,
    DROP COLUMN IF EXISTS source_attributes,
    DROP COLUMN IF EXISTS temu_sync_enabled,
    DROP COLUMN IF EXISTS missing_from_source,
    DROP COLUMN IF EXISTS weight_unit,
    DROP COLUMN IF EXISTS weight,
    DROP COLUMN IF EXISTS release_date,
    DROP COLUMN IF EXISTS image_url,
    DROP COLUMN IF EXISTS product_url,
    DROP COLUMN IF EXISTS precision_score,
    DROP COLUMN IF EXISTS source_status,
    DROP COLUMN IF EXISTS delivery_time_days,
    DROP COLUMN IF EXISTS branch_suffix,
    DROP COLUMN IF EXISTS group_name,
    DROP COLUMN IF EXISTS family,
    DROP COLUMN IF EXISTS category,
    DROP COLUMN IF EXISTS label,
    DROP COLUMN IF EXISTS format,
    DROP COLUMN IF EXISTS title,
    DROP COLUMN IF EXISTS artist,
    DROP COLUMN IF EXISTS feed_name
SQL);
    }
}
