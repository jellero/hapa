<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAccessControl extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE app_users (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    role VARCHAR(32) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT app_users_role_check CHECK (role IN ('administrator', 'operator', 'viewer')),
    CONSTRAINT app_users_status_check CHECK (status IN ('active', 'suspended', 'retired')),
    CONSTRAINT app_users_values_check CHECK (
        btrim(email) <> '' AND btrim(display_name) <> '' AND btrim(password_hash) <> ''
        AND failed_login_attempts >= 0
    )
)
SQL);
        $this->execute('CREATE UNIQUE INDEX app_users_email_unique ON app_users (lower(email))');

        $this->execute(<<<'SQL'
CREATE TABLE web_sessions (
    id BIGSERIAL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL UNIQUE,
    user_id CHAR(36) NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    last_seen_at TIMESTAMPTZ NOT NULL,
    user_agent_hash CHAR(64) NOT NULL,
    ip_address_hash CHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    CONSTRAINT web_sessions_user_fk
        FOREIGN KEY (user_id) REFERENCES app_users (id) ON DELETE CASCADE ON UPDATE CASCADE
)
SQL);
        $this->execute('CREATE INDEX web_sessions_expiry_idx ON web_sessions (expires_at)');
        $this->execute('CREATE INDEX web_sessions_user_idx ON web_sessions (user_id, last_seen_at DESC) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS web_sessions');
        $this->execute('DROP TABLE IF EXISTS app_users');
    }
}
