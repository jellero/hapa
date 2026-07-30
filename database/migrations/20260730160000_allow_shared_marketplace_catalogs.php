<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AllowSharedMarketplaceCatalogs extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            'ALTER TABLE commercial_catalog_marketplaces DROP CONSTRAINT IF EXISTS commercial_catalog_marketplace_owner_unique',
        );
        $this->execute(
            'CREATE INDEX IF NOT EXISTS commercial_catalog_marketplaces_marketplace_idx
             ON commercial_catalog_marketplaces (marketplace_id, commercial_catalog_id)',
        );
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS commercial_catalog_marketplaces_marketplace_idx');
        $this->execute(
            'ALTER TABLE commercial_catalog_marketplaces
             ADD CONSTRAINT commercial_catalog_marketplace_owner_unique UNIQUE (marketplace_id)',
        );
    }
}
