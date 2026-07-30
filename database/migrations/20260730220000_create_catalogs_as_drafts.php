<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCatalogsAsDrafts extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE commercial_catalogs ALTER COLUMN enabled SET DEFAULT FALSE');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE commercial_catalogs ALTER COLUMN enabled SET DEFAULT TRUE');
    }
}
