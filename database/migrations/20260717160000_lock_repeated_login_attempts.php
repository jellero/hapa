<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LockRepeatedLoginAttempts extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE app_users ADD COLUMN locked_until TIMESTAMPTZ NULL');
        $this->execute('CREATE INDEX app_users_locked_idx ON app_users (locked_until) WHERE locked_until IS NOT NULL');
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS app_users_locked_idx');
        $this->execute('ALTER TABLE app_users DROP COLUMN IF EXISTS locked_until');
    }
}
