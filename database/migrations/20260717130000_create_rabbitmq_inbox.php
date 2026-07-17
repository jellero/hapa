<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRabbitMqInbox extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE inbox_messages (
    id BIGSERIAL PRIMARY KEY,
    message_id VARCHAR(200) NOT NULL UNIQUE,
    event_type VARCHAR(200) NOT NULL,
    schema_version INTEGER NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL,
    correlation_id VARCHAR(200) NOT NULL,
    causation_id VARCHAR(200) NULL,
    payload JSONB NOT NULL,
    status VARCHAR(24) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 1,
    error TEXT NULL,
    received_at TIMESTAMPTZ NOT NULL,
    processed_at TIMESTAMPTZ NULL,
    failed_at TIMESTAMPTZ NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT inbox_schema_version_check CHECK (schema_version > 0),
    CONSTRAINT inbox_attempts_check CHECK (attempts > 0),
    CONSTRAINT inbox_status_check CHECK (status IN ('processing', 'processed', 'failed')),
    CONSTRAINT inbox_payload_check CHECK (jsonb_typeof(payload) = 'object')
)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX inbox_status_received_idx
    ON inbox_messages (status, received_at, id)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX inbox_event_received_idx
    ON inbox_messages (event_type, received_at DESC)
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS inbox_messages');
    }
}
