<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RecalculateBackorderSellableQuantity extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
UPDATE catalog_items item
SET sellable_quantity = GREATEST(
        0,
        source.available_quantity + source.backorder_quantity - item.safety_stock
    ),
    offers_calculated_at = NOW(),
    updated_at = NOW()
FROM supplier_catalog_items source
JOIN suppliers supplier ON supplier.id = source.supplier_id AND supplier.code = 'space'
WHERE source.catalog_item_id = item.id
SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
UPDATE catalog_items item
SET sellable_quantity = GREATEST(0, source.available_quantity - item.safety_stock),
    offers_calculated_at = NOW(),
    updated_at = NOW()
FROM supplier_catalog_items source
JOIN suppliers supplier ON supplier.id = source.supplier_id AND supplier.code = 'space'
WHERE source.catalog_item_id = item.id
SQL);
    }
}
