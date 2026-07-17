<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IngestSpaceCatalogProducts extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE catalog_items
    ADD COLUMN name VARCHAR(255) NULL,
    ADD COLUMN description TEXT NULL,
    ADD COLUMN onboarding_status VARCHAR(32) NOT NULL DEFAULT 'approved',
    ADD CONSTRAINT catalog_items_onboarding_status_check
        CHECK (onboarding_status IN ('pending_review', 'approved', 'rejected')),
    ADD CONSTRAINT catalog_items_content_check CHECK (
        (name IS NULL OR btrim(name) <> '')
        AND (description IS NULL OR btrim(description) <> '')
    )
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX catalog_items_onboarding_review_idx
    ON catalog_items (created_at, id)
    WHERE onboarding_status = 'pending_review'
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX catalog_items_ean_lookup_idx
    ON catalog_items (ean, id)
    WHERE ean IS NOT NULL
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE supplier_catalog_observations (
    id BIGSERIAL PRIMARY KEY,
    message_id VARCHAR(200) NOT NULL,
    supplier_id BIGINT NOT NULL,
    external_item_id VARCHAR(160) NOT NULL,
    source_version VARCHAR(200) NOT NULL,
    catalog_item_id BIGINT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'processing',
    outcome VARCHAR(48) NULL,
    reason VARCHAR(1000) NULL,
    payload JSONB NOT NULL,
    observed_at TIMESTAMPTZ NOT NULL,
    processed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT supplier_catalog_observations_message_unique UNIQUE (message_id),
    CONSTRAINT supplier_catalog_observations_source_unique
        UNIQUE (supplier_id, external_item_id, source_version),
    CONSTRAINT supplier_catalog_observations_supplier_fk
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT supplier_catalog_observations_catalog_fk
        FOREIGN KEY (catalog_item_id) REFERENCES catalog_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT supplier_catalog_observations_status_check
        CHECK (status IN ('processing', 'applied', 'manual_review', 'ignored')),
    CONSTRAINT supplier_catalog_observations_outcome_check CHECK (
        outcome IS NULL OR outcome IN (
            'created_pending_review', 'linked_existing', 'updated',
            'duplicate', 'ignored_stale', 'identity_conflict'
        )
    ),
    CONSTRAINT supplier_catalog_observations_values_check CHECK (
        btrim(message_id) <> ''
        AND btrim(external_item_id) <> ''
        AND btrim(source_version) <> ''
        AND jsonb_typeof(payload) = 'object'
        AND (reason IS NULL OR btrim(reason) <> '')
    )
)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX supplier_catalog_observations_review_idx
    ON supplier_catalog_observations (created_at, id)
    WHERE status = 'manual_review'
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS supplier_catalog_observations');
        $this->execute('DROP INDEX IF EXISTS catalog_items_ean_lookup_idx');
        $this->execute('DROP INDEX IF EXISTS catalog_items_onboarding_review_idx');
        $this->execute('ALTER TABLE catalog_items DROP CONSTRAINT IF EXISTS catalog_items_content_check');
        $this->execute('ALTER TABLE catalog_items DROP CONSTRAINT IF EXISTS catalog_items_onboarding_status_check');
        $this->execute('ALTER TABLE catalog_items DROP COLUMN IF EXISTS onboarding_status');
        $this->execute('ALTER TABLE catalog_items DROP COLUMN IF EXISTS description');
        $this->execute('ALTER TABLE catalog_items DROP COLUMN IF EXISTS name');
    }
}
