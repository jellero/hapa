<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OptimizeCatalogReadPaths extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS supplier_catalog_items_opening_idx
    ON supplier_catalog_items (supplier_id, observed_at DESC NULLS LAST, catalog_item_id)
    INCLUDE (id)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS marketplace_offers_catalog_item_idx
    ON marketplace_offers (catalog_item_id)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS supplier_catalog_items_feed_filter_idx
    ON supplier_catalog_items (supplier_id, feed_name)
    WHERE feed_name IS NOT NULL
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS supplier_catalog_items_format_filter_idx
    ON supplier_catalog_items (supplier_id, format)
    WHERE format IS NOT NULL
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS supplier_catalog_items_supplier_filter_idx
    ON supplier_catalog_items (supplier_id, space_supplier_id)
    WHERE space_supplier_id IS NOT NULL
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS audit_logs_created_at_idx
    ON audit_logs (created_at DESC)
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX IF NOT EXISTS shipments_created_at_idx
    ON shipments (created_at DESC)
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS shipments_created_at_idx');
        $this->execute('DROP INDEX IF EXISTS audit_logs_created_at_idx');
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_supplier_filter_idx');
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_format_filter_idx');
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_feed_filter_idx');
        $this->execute('DROP INDEX IF EXISTS marketplace_offers_catalog_item_idx');
        $this->execute('DROP INDEX IF EXISTS supplier_catalog_items_opening_idx');
    }
}
