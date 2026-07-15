<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class HardenHapaCore extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE orders
    ADD CONSTRAINT orders_status_check CHECK (status IN (
        'new', 'accepted', 'waiting_address', 'imported', 'sent_to_space',
        'waiting_goods', 'complete', 'partial_available', 'picking',
        'partial_confirmed', 'ready_for_gls', 'label_available',
        'tracking_sent', 'completed', 'completed_partial', 'cancelled',
        'manual_review'
    )),
    ADD CONSTRAINT orders_currency_check CHECK (currency ~ '^[A-Z]{3}$'),
    ADD CONSTRAINT orders_version_check CHECK (version > 0)
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE order_lines
    ADD COLUMN external_line_id VARCHAR(160) NULL,
    ADD CONSTRAINT order_lines_quantities_non_negative CHECK (
        quantity_ordered > 0
        AND quantity_available >= 0
        AND quantity_to_ship >= 0
        AND quantity_to_cancel >= 0
    ),
    ADD CONSTRAINT order_lines_quantities_consistent CHECK (
        quantity_available <= quantity_ordered
        AND quantity_to_ship + quantity_to_cancel <= quantity_ordered
    )
SQL);

        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX order_lines_external_line_unique
    ON order_lines (order_id, external_line_id)
    WHERE external_line_id IS NOT NULL
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE shipments
    ADD CONSTRAINT shipments_packages_check CHECK (packages > 0),
    ADD CONSTRAINT shipments_weight_check CHECK (weight_kg IS NULL OR weight_kg > 0)
SQL);

        $this->execute('DROP INDEX IF EXISTS shipments_tracking_number');
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX shipments_provider_tracking_unique
    ON shipments (provider, tracking_number)
    WHERE tracking_number IS NOT NULL
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE outbox_messages
    ADD COLUMN lock_token VARCHAR(64) NULL,
    ADD COLUMN locked_by VARCHAR(160) NULL,
    ADD COLUMN max_attempts INTEGER NOT NULL DEFAULT 10,
    ADD COLUMN failed_at TIMESTAMP NULL,
    ADD CONSTRAINT outbox_status_check CHECK (status IN ('pending', 'processing', 'retry', 'completed', 'dead')),
    ADD CONSTRAINT outbox_attempts_check CHECK (attempts >= 0 AND max_attempts > 0 AND attempts <= max_attempts)
SQL);

        $this->execute(<<<'SQL'
CREATE INDEX outbox_claim_index
    ON outbox_messages (available_at, id)
    WHERE status IN ('pending', 'retry')
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE external_deliveries
    ADD CONSTRAINT external_deliveries_attempt_check CHECK (attempt > 0),
    ADD CONSTRAINT external_deliveries_status_check CHECK (
        status IN ('pending', 'processing', 'success', 'temporary_error', 'permanent_error')
    )
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE external_deliveries DROP CONSTRAINT IF EXISTS external_deliveries_status_check');
        $this->execute('ALTER TABLE external_deliveries DROP CONSTRAINT IF EXISTS external_deliveries_attempt_check');

        $this->execute('DROP INDEX IF EXISTS outbox_claim_index');
        $this->execute('ALTER TABLE outbox_messages DROP CONSTRAINT IF EXISTS outbox_attempts_check');
        $this->execute('ALTER TABLE outbox_messages DROP CONSTRAINT IF EXISTS outbox_status_check');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS failed_at');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS max_attempts');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS locked_by');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS lock_token');

        $this->execute('DROP INDEX IF EXISTS shipments_provider_tracking_unique');
        $this->execute('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_weight_check');
        $this->execute('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_packages_check');

        $this->execute('DROP INDEX IF EXISTS order_lines_external_line_unique');
        $this->execute('ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_quantities_consistent');
        $this->execute('ALTER TABLE order_lines DROP CONSTRAINT IF EXISTS order_lines_quantities_non_negative');
        $this->execute('ALTER TABLE order_lines DROP COLUMN IF EXISTS external_line_id');

        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_version_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_currency_check');
        $this->execute('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
    }
}
