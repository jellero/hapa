<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ConfigureIntegrationAccounts extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE integration_accounts (
    id BIGSERIAL PRIMARY KEY,
    provider_code VARCHAR(64) NOT NULL,
    code VARCHAR(96) NOT NULL UNIQUE,
    display_name VARCHAR(160) NOT NULL,
    environment VARCHAR(24) NOT NULL,
    description VARCHAR(1000) NULL,
    desired_status VARCHAR(24) NOT NULL DEFAULT 'disabled',
    configuration_version INTEGER NOT NULL DEFAULT 1,
    secret_status VARCHAR(24) NOT NULL DEFAULT 'missing',
    secret_version INTEGER NOT NULL DEFAULT 0,
    secret_rotated_at TIMESTAMPTZ NULL,
    connection_test_status VARCHAR(24) NOT NULL DEFAULT 'never',
    connection_tested_at TIMESTAMPTZ NULL,
    technical_checked_at TIMESTAMPTZ NULL,
    token_expires_at TIMESTAMPTZ NULL,
    last_error VARCHAR(1000) NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT integration_accounts_provider_check CHECK (provider_code IN ('sellrapido', 'space', 'gls', 'brt', 'amazon', 'temu')),
    CONSTRAINT integration_accounts_environment_check CHECK (environment IN ('sandbox', 'production')),
    CONSTRAINT integration_accounts_status_check CHECK (desired_status IN ('disabled', 'pilot', 'active', 'suspended', 'retired')),
    CONSTRAINT integration_accounts_secret_check CHECK (secret_status IN ('missing', 'configured', 'revoked', 'error')),
    CONSTRAINT integration_accounts_test_check CHECK (connection_test_status IN ('never', 'pending', 'passed', 'failed')),
    CONSTRAINT integration_accounts_values_check CHECK (
        btrim(code) <> '' AND btrim(display_name) <> '' AND configuration_version > 0 AND secret_version >= 0
    )
)
SQL);
        $this->execute('CREATE INDEX integration_accounts_provider_idx ON integration_accounts (provider_code, desired_status, display_name)');
        $this->execute(<<<'SQL'
CREATE TABLE integration_account_capabilities (
    integration_account_id BIGINT NOT NULL,
    capability VARCHAR(96) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (integration_account_id, capability),
    CONSTRAINT integration_account_capabilities_account_fk
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT integration_account_capabilities_value_check CHECK (btrim(capability) <> '')
)
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE integration_account_settings (
    integration_account_id BIGINT NOT NULL,
    setting_key VARCHAR(96) NOT NULL,
    setting_value JSONB NOT NULL,
    PRIMARY KEY (integration_account_id, setting_key),
    CONSTRAINT integration_account_settings_account_fk
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT integration_account_settings_key_check CHECK (btrim(setting_key) <> '')
)
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE integration_account_history (
    id BIGSERIAL PRIMARY KEY,
    integration_account_id BIGINT NOT NULL,
    configuration_version INTEGER NOT NULL,
    action VARCHAR(64) NOT NULL,
    snapshot JSONB NOT NULL,
    actor_id CHAR(36) NOT NULL,
    correlation_id VARCHAR(160) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT integration_account_history_account_fk
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT integration_account_history_actor_fk
        FOREIGN KEY (actor_id) REFERENCES app_users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT integration_account_history_version_unique UNIQUE (integration_account_id, configuration_version),
    CONSTRAINT integration_account_history_values_check CHECK (
        configuration_version > 0 AND btrim(action) <> '' AND jsonb_typeof(snapshot) = 'object'
    )
)
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS integration_account_history');
        $this->execute('DROP TABLE IF EXISTS integration_account_settings');
        $this->execute('DROP TABLE IF EXISTS integration_account_capabilities');
        $this->execute('DROP TABLE IF EXISTS integration_accounts');
    }
}
