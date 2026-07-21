<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCatalogSynchronization extends AbstractMigration
{
    public function up(): void
    {
        $this->migratePart1();
        $this->migratePart2();
    }


    private function migratePart1(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE catalog_items (
    id BIGSERIAL PRIMARY KEY,
    sku VARCHAR(160) NOT NULL,
    ean VARCHAR(32) NULL,
    space_item_id VARCHAR(160) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    space_price_minor BIGINT NULL,
    space_available_quantity INTEGER NOT NULL DEFAULT 0,
    safety_stock INTEGER NOT NULL DEFAULT 0,
    sellable_quantity INTEGER GENERATED ALWAYS AS (
        GREATEST(space_available_quantity - safety_stock, 0)
    ) STORED,
    source_version VARCHAR(160) NULL,
    last_space_sync_at TIMESTAMPTZ NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    version INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT catalog_items_sku_unique UNIQUE (sku),
    CONSTRAINT catalog_items_identity_check CHECK (
        BTRIM(sku) <> ''
        AND (ean IS NULL OR BTRIM(ean) <> '')
        AND (space_item_id IS NULL OR BTRIM(space_item_id) <> '')
        AND (source_version IS NULL OR BTRIM(source_version) <> '')
    ),
    CONSTRAINT catalog_items_currency_check CHECK (currency ~ '^[A-Z]{3}$'),
    CONSTRAINT catalog_items_price_check CHECK (space_price_minor IS NULL OR space_price_minor >= 0),
    CONSTRAINT catalog_items_quantity_check CHECK (space_available_quantity >= 0 AND safety_stock >= 0),
    CONSTRAINT catalog_items_version_check CHECK (version > 0)
)
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX catalog_items_space_id_unique
    ON catalog_items (space_item_id)
    WHERE space_item_id IS NOT NULL
SQL);
        $this->execute('CREATE INDEX catalog_items_sync_idx ON catalog_items (last_space_sync_at, id) WHERE active');

        $this->execute(<<<'SQL'
CREATE TABLE pricing_rules (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(96) NOT NULL,
    name VARCHAR(160) NOT NULL,
    scope VARCHAR(32) NOT NULL,
    marketplace_id INTEGER NULL,
    sku VARCHAR(160) NULL,
    adjustment_type VARCHAR(32) NOT NULL,
    adjustment_value BIGINT NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    minimum_price_minor BIGINT NULL,
    maximum_price_minor BIGINT NULL,
    priority INTEGER NOT NULL DEFAULT 100,
    enabled BOOLEAN NOT NULL DEFAULT FALSE,
    valid_from TIMESTAMPTZ NULL,
    valid_until TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pricing_rules_code_unique UNIQUE (code),
    CONSTRAINT pricing_rules_marketplace_fk FOREIGN KEY (marketplace_id) REFERENCES marketplaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT pricing_rules_scope_check CHECK (scope IN ('global', 'marketplace', 'sku', 'marketplace_sku')),
    CONSTRAINT pricing_rules_identity_check CHECK (BTRIM(code) <> '' AND BTRIM(name) <> '' AND (sku IS NULL OR BTRIM(sku) <> '')),
    CONSTRAINT pricing_rules_target_check CHECK (
        (scope = 'global' AND marketplace_id IS NULL AND sku IS NULL)
        OR (scope = 'marketplace' AND marketplace_id IS NOT NULL AND sku IS NULL)
        OR (scope = 'sku' AND marketplace_id IS NULL AND sku IS NOT NULL)
        OR (scope = 'marketplace_sku' AND marketplace_id IS NOT NULL AND sku IS NOT NULL)
    ),
    CONSTRAINT pricing_rules_adjustment_type_check CHECK (adjustment_type IN ('percentage', 'fixed_amount', 'fixed_price')),
    CONSTRAINT pricing_rules_adjustment_check CHECK (
        adjustment_value >= 0
        AND (adjustment_type <> 'percentage' OR adjustment_value <= 100000)
        AND (adjustment_type <> 'fixed_price' OR adjustment_value > 0)
    ),
    CONSTRAINT pricing_rules_currency_check CHECK (currency ~ '^[A-Z]{3}$'),
    CONSTRAINT pricing_rules_boundaries_check CHECK (
        (minimum_price_minor IS NULL OR minimum_price_minor >= 0)
        AND (maximum_price_minor IS NULL OR maximum_price_minor >= 0)
        AND (minimum_price_minor IS NULL OR maximum_price_minor IS NULL OR minimum_price_minor <= maximum_price_minor)
    ),
    CONSTRAINT pricing_rules_priority_check CHECK (priority BETWEEN 0 AND 100000),
    CONSTRAINT pricing_rules_validity_check CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_from < valid_until)
)
SQL);
    }

    private function migratePart2(): void
    {
        $this->execute(<<<'SQL'
CREATE INDEX pricing_rules_match_idx
    ON pricing_rules (marketplace_id, sku, priority DESC, id)
    WHERE enabled
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE marketplace_offers (
    id BIGSERIAL PRIMARY KEY,
    catalog_item_id BIGINT NOT NULL,
    marketplace_id INTEGER NOT NULL,
    external_offer_id VARCHAR(160) NULL,
    desired_price_minor BIGINT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    desired_quantity INTEGER NOT NULL DEFAULT 0,
    applied_pricing_rule_id BIGINT NULL,
    source_version INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(32) NOT NULL DEFAULT 'disabled',
    last_idempotency_key VARCHAR(160) NULL,
    remote_version VARCHAR(160) NULL,
    last_synced_at TIMESTAMPTZ NULL,
    last_error TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT marketplace_offers_item_fk FOREIGN KEY (catalog_item_id) REFERENCES catalog_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT marketplace_offers_marketplace_fk FOREIGN KEY (marketplace_id) REFERENCES marketplaces (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT marketplace_offers_rule_fk FOREIGN KEY (applied_pricing_rule_id) REFERENCES pricing_rules (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT marketplace_offers_item_marketplace_unique UNIQUE (catalog_item_id, marketplace_id),
    CONSTRAINT marketplace_offers_identity_check CHECK (
        (external_offer_id IS NULL OR BTRIM(external_offer_id) <> '')
        AND (last_idempotency_key IS NULL OR BTRIM(last_idempotency_key) <> '')
        AND (remote_version IS NULL OR BTRIM(remote_version) <> '')
    ),
    CONSTRAINT marketplace_offers_price_check CHECK (desired_price_minor IS NULL OR desired_price_minor >= 0),
    CONSTRAINT marketplace_offers_currency_check CHECK (currency ~ '^[A-Z]{3}$'),
    CONSTRAINT marketplace_offers_quantity_check CHECK (desired_quantity >= 0),
    CONSTRAINT marketplace_offers_version_check CHECK (source_version > 0),
    CONSTRAINT marketplace_offers_status_check CHECK (status IN ('disabled', 'pending', 'syncing', 'synced', 'error'))
)
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX marketplace_offers_idempotency_unique
    ON marketplace_offers (last_idempotency_key)
    WHERE last_idempotency_key IS NOT NULL
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX marketplace_offers_publish_idx
    ON marketplace_offers (updated_at, id)
    WHERE status IN ('pending', 'error')
SQL);

        $this->execute(<<<'SQL'
UPDATE automation_jobs
SET code = 'sync_space_catalog',
    name = 'Sincronizza catalogo Space',
    description = 'Acquisisce via API prezzi e disponibilità Space e ricalcola lo stock vendibile.',
    event_type = 'automation.space.sync_catalog.requested',
    enabled = FALSE,
    last_status = 'idle',
    last_error = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'refresh_stock_availability'
SQL);
        $this->execute(<<<'SQL'
INSERT INTO automation_jobs (
    code, name, description, event_type, interval_seconds, requires_manual_confirmation
) VALUES (
    'publish_marketplace_offers',
    'Pubblica offerte marketplace',
    'Sincronizza via API prezzi ricalcolati e disponibilità vendibile sui marketplace abilitati.',
    'automation.marketplace.publish_offers.requested',
    600,
    FALSE
)
SQL);
    }

    public function down(): void
    {
        $this->execute("DELETE FROM automation_jobs WHERE code = 'publish_marketplace_offers'");
        $this->execute(<<<'SQL'
UPDATE automation_jobs
SET code = 'refresh_stock_availability',
    name = 'Aggiorna disponibilità',
    description = 'Acquisisce da Space le quantità ricevute e disponibili.',
    event_type = 'automation.space.refresh_availability.requested',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'sync_space_catalog'
SQL);
        $this->execute('DROP TABLE IF EXISTS marketplace_offers');
        $this->execute('DROP TABLE IF EXISTS pricing_rules');
        $this->execute('DROP TABLE IF EXISTS catalog_items');
    }
}
