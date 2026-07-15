<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ConsolidateSchema extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE orders
    ALTER COLUMN shipping_address TYPE JSONB USING shipping_address::jsonb,
    ALTER COLUMN accepted_at TYPE TIMESTAMPTZ USING accepted_at AT TIME ZONE 'UTC',
    ALTER COLUMN completed_at TYPE TIMESTAMPTZ USING completed_at AT TIME ZONE 'UTC',
    ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
    ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE order_lines
    ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
    ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE shipments
    ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
    ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC',
    ADD CONSTRAINT shipments_status_check CHECK (
        status IN ('pending', 'created', 'label_available', 'shipped', 'cancelled', 'error')
    )
SQL);
        $this->execute(<<<'SQL'
CREATE UNIQUE INDEX shipments_provider_external_unique
    ON shipments (provider, external_shipment_id)
    WHERE external_shipment_id IS NOT NULL
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE outbox_messages
    ALTER COLUMN payload TYPE JSONB USING payload::jsonb,
    ALTER COLUMN available_at TYPE TIMESTAMPTZ USING available_at AT TIME ZONE 'UTC',
    ALTER COLUMN locked_at TYPE TIMESTAMPTZ USING locked_at AT TIME ZONE 'UTC',
    ALTER COLUMN completed_at TYPE TIMESTAMPTZ USING completed_at AT TIME ZONE 'UTC',
    ALTER COLUMN failed_at TYPE TIMESTAMPTZ USING failed_at AT TIME ZONE 'UTC',
    ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
    ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE external_deliveries
    ALTER COLUMN request_payload TYPE JSONB USING request_payload::jsonb,
    ALTER COLUMN response_payload TYPE JSONB USING response_payload::jsonb,
    ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
    ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC',
    ADD CONSTRAINT external_deliveries_http_status_check CHECK (
        http_status IS NULL OR http_status BETWEEN 100 AND 599
    )
SQL);

        $this->execute(<<<'SQL'
ALTER TABLE audit_logs
    ALTER COLUMN before_data TYPE JSONB USING before_data::jsonb,
    ALTER COLUMN after_data TYPE JSONB USING after_data::jsonb,
    ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE external_deliveries DROP CONSTRAINT IF EXISTS external_deliveries_http_status_check');
        $this->execute('DROP INDEX IF EXISTS shipments_provider_external_unique');
        $this->execute('ALTER TABLE shipments DROP CONSTRAINT IF EXISTS shipments_status_check');
    }
}
