<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TrackAutomationConfiguration extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE integration_accounts
    ADD COLUMN automation_configuration_version INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN automation_configured_at TIMESTAMPTZ NULL,
    ADD CONSTRAINT integration_accounts_automation_version_check CHECK (
        automation_configuration_version >= 0
        AND automation_configuration_version <= configuration_version
    )
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE integration_accounts DROP CONSTRAINT IF EXISTS integration_accounts_automation_version_check');
        $this->execute('ALTER TABLE integration_accounts DROP COLUMN IF EXISTS automation_configured_at');
        $this->execute('ALTER TABLE integration_accounts DROP COLUMN IF EXISTS automation_configuration_version');
    }
}
