<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveEmbeddedAutomationRuntime extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('DROP TABLE IF EXISTS automation_jobs');
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE automation_jobs (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(96) NOT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(500) NOT NULL,
    event_type VARCHAR(160) NOT NULL,
    interval_seconds INTEGER NOT NULL DEFAULT 600,
    enabled BOOLEAN NOT NULL DEFAULT FALSE,
    requires_manual_confirmation BOOLEAN NOT NULL DEFAULT FALSE,
    next_run_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_status VARCHAR(32) NOT NULL DEFAULT 'idle',
    last_started_at TIMESTAMPTZ NULL,
    last_completed_at TIMESTAMPTZ NULL,
    last_error TEXT NULL,
    locked_at TIMESTAMPTZ NULL,
    locked_by VARCHAR(160) NULL,
    lock_token VARCHAR(64) NULL,
    cursor JSONB NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT automation_jobs_code_unique UNIQUE (code),
    CONSTRAINT automation_jobs_interval_check CHECK (interval_seconds >= 60),
    CONSTRAINT automation_jobs_status_check CHECK (last_status IN ('idle', 'running', 'success', 'error')),
    CONSTRAINT automation_jobs_cursor_check CHECK (cursor IS NULL OR jsonb_typeof(cursor) = 'object')
)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX automation_jobs_due_idx
    ON automation_jobs (next_run_at, id)
    WHERE enabled
SQL);
    }
}
