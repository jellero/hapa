<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAutomationRuntime extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE outbox_messages
    ADD COLUMN schema_version INTEGER NOT NULL DEFAULT 1,
    ADD COLUMN correlation_id VARCHAR(160) NULL,
    ADD CONSTRAINT outbox_schema_version_check CHECK (schema_version > 0)
SQL);
        $this->execute("UPDATE outbox_messages SET correlation_id = 'legacy-outbox-' || id WHERE correlation_id IS NULL");
        $this->execute('ALTER TABLE outbox_messages ALTER COLUMN correlation_id SET NOT NULL');

        $this->execute(<<<'SQL'
ALTER TABLE audit_logs
    ADD COLUMN source_outbox_id BIGINT NULL,
    ADD CONSTRAINT audit_logs_source_outbox_unique UNIQUE (source_outbox_id)
SQL);

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

        $this->execute(<<<'SQL'
INSERT INTO automation_jobs (
    code, name, description, event_type, interval_seconds, requires_manual_confirmation
) VALUES
    ('accept_complete_orders', 'Accetta ordini completi', 'Accetta gli ordini marketplace completi e idonei.', 'automation.orders.accept_complete.requested', 600, FALSE),
    ('recover_shipping_addresses', 'Recupera indirizzi', 'Recupera e normalizza gli indirizzi dopo l’accettazione.', 'automation.orders.recover_addresses.requested', 600, FALSE),
    ('import_work_orders', 'Importa ordini di lavoro', 'Importa in HAPA gli ordini pronti per la lavorazione.', 'automation.orders.import.requested', 600, FALSE),
    ('export_space_csv', 'Esporta verso Space', 'Genera e trasferisce il CSV previsto dal flusso FTP Space.', 'automation.space.export_csv.requested', 600, FALSE),
    ('refresh_stock_availability', 'Aggiorna disponibilità', 'Acquisisce da Space le quantità ricevute e disponibili.', 'automation.space.refresh_availability.requested', 600, FALSE),
    ('manage_confirmed_partials', 'Gestisci parziali confermati', 'Prosegue soltanto dopo la conferma manuale delle quantità parziali.', 'automation.orders.process_confirmed_partials.requested', 600, TRUE),
    ('retry_temporary_errors', 'Recupera errori temporanei', 'Recupera lock scaduti e ripianifica gli errori classificati come temporanei.', 'automation.outbox.recover.requested', 600, FALSE)
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS automation_jobs');
        $this->execute('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_source_outbox_unique');
        $this->execute('ALTER TABLE audit_logs DROP COLUMN IF EXISTS source_outbox_id');
        $this->execute('ALTER TABLE outbox_messages DROP CONSTRAINT IF EXISTS outbox_schema_version_check');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS correlation_id');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS schema_version');
    }
}
