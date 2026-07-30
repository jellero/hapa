<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ModelSpaceBackorder extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE supplier_catalog_items
    ADD COLUMN backorder_quantity INTEGER NOT NULL DEFAULT 0,
    ADD CONSTRAINT supplier_catalog_backorder_quantity_check CHECK (backorder_quantity >= 0)
SQL);
        $this->execute(<<<'SQL'
UPDATE supplier_catalog_items
SET backorder_quantity = CASE
    WHEN COALESCE(source_attributes->>'stock_qty_fornitore', '') ~ '^[0-9]+$'
        THEN (source_attributes->>'stock_qty_fornitore')::INTEGER
    ELSE 0
END
SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE supplier_catalog_items
    DROP CONSTRAINT IF EXISTS supplier_catalog_backorder_quantity_check,
    DROP COLUMN IF EXISTS backorder_quantity
SQL);
    }
}
