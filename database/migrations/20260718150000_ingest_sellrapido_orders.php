<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class IngestSellRapidoOrders extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE orders
    ADD COLUMN provider_order_id VARCHAR(160) NULL,
    ADD COLUMN provider_status VARCHAR(32) NULL,
    ADD COLUMN source_version VARCHAR(200) NULL,
    ADD COLUMN source_modified_at TIMESTAMPTZ NULL,
    ADD COLUMN source_observed_at TIMESTAMPTZ NULL,
    ADD COLUMN marketplace_code VARCHAR(96) NULL,
    ADD COLUMN channel_code VARCHAR(96) NULL,
    ADD COLUMN marketplace_fee_total_minor BIGINT NULL,
    ADD CONSTRAINT orders_provider_values_check CHECK (
        (provider_order_id IS NULL OR btrim(provider_order_id) <> '')
        AND (provider_status IS NULL OR provider_status IN ('standby', 'accepted', 'sent', 'cancelled'))
        AND (source_version IS NULL OR btrim(source_version) <> '')
        AND (marketplace_code IS NULL OR btrim(marketplace_code) <> '')
        AND (channel_code IS NULL OR btrim(channel_code) <> '')
        AND (marketplace_fee_total_minor IS NULL OR marketplace_fee_total_minor >= 0)
    )
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX orders_marketplace_account_provider_unique
    ON orders (marketplace_account_id, provider_order_id)
    WHERE marketplace_account_id IS NOT NULL AND provider_order_id IS NOT NULL
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE marketplace_order_observations (
    id BIGSERIAL PRIMARY KEY,
    message_id VARCHAR(200) NOT NULL,
    marketplace_account_id BIGINT NOT NULL,
    provider_order_id VARCHAR(160) NOT NULL,
    source_version VARCHAR(200) NOT NULL,
    order_id INTEGER NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'processing',
    outcome VARCHAR(48) NULL,
    reason VARCHAR(500) NULL,
    modified_at TIMESTAMPTZ NOT NULL,
    observed_at TIMESTAMPTZ NOT NULL,
    processed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT marketplace_order_observations_message_unique UNIQUE (message_id),
    CONSTRAINT marketplace_order_observations_source_unique UNIQUE (
        marketplace_account_id, provider_order_id, source_version
    ),
    CONSTRAINT marketplace_order_observations_account_fk
        FOREIGN KEY (marketplace_account_id) REFERENCES marketplace_accounts (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT marketplace_order_observations_order_fk
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT marketplace_order_observations_status_check CHECK (
        status IN ('processing', 'applied', 'ignored', 'failed')
    ),
    CONSTRAINT marketplace_order_observations_values_check CHECK (
        btrim(message_id) <> ''
        AND btrim(provider_order_id) <> ''
        AND btrim(source_version) <> ''
        AND (outcome IS NULL OR btrim(outcome) <> '')
        AND (reason IS NULL OR btrim(reason) <> '')
    )
)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX marketplace_order_observations_timeline_idx
    ON marketplace_order_observations (marketplace_account_id, provider_order_id, observed_at DESC)
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS marketplace_order_observations');
        $this->execute('DROP INDEX IF EXISTS orders_marketplace_account_provider_unique');
        $this->execute(<<<'SQL'
ALTER TABLE orders
    DROP CONSTRAINT IF EXISTS orders_provider_values_check,
    DROP COLUMN IF EXISTS provider_order_id,
    DROP COLUMN IF EXISTS provider_status,
    DROP COLUMN IF EXISTS source_version,
    DROP COLUMN IF EXISTS source_modified_at,
    DROP COLUMN IF EXISTS source_observed_at,
    DROP COLUMN IF EXISTS marketplace_code,
    DROP COLUMN IF EXISTS channel_code,
    DROP COLUMN IF EXISTS marketplace_fee_total_minor
SQL);
    }
}
