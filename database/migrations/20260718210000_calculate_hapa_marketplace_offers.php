<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CalculateHapaMarketplaceOffers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE catalog_item_history DROP CONSTRAINT catalog_item_history_values_check');
        $this->execute(<<<'SQL'
ALTER TABLE catalog_item_history
    ADD CONSTRAINT catalog_item_history_values_check CHECK (
        version > 0
        AND action IN ('approved', 'rejected', 'safety_stock_updated')
        AND jsonb_typeof(snapshot) = 'object'
    )
SQL);
        $this->execute('ALTER TABLE catalog_items DROP COLUMN sellable_quantity');
        $this->execute(<<<'SQL'
ALTER TABLE catalog_items
    ADD COLUMN sellable_quantity INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN offers_calculated_at TIMESTAMPTZ NULL,
    ADD CONSTRAINT catalog_items_sellable_quantity_check CHECK (sellable_quantity >= 0)
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE marketplace_offers
    ADD COLUMN calculated_at TIMESTAMPTZ NULL
SQL);
        $this->execute(<<<'SQL'
UPDATE catalog_items AS item
SET sellable_quantity = GREATEST(space_offer.available_quantity - item.safety_stock, 0),
    offers_calculated_at = CURRENT_TIMESTAMP
FROM supplier_catalog_items AS space_offer
JOIN suppliers AS supplier ON supplier.id = space_offer.supplier_id
WHERE supplier.code = 'space'
  AND space_offer.catalog_item_id = item.id
  AND space_offer.active
SQL);
        $this->execute(<<<'SQL'
WITH calculated AS (
    SELECT item.id AS catalog_item_id,
           marketplace.id AS marketplace_id,
           COALESCE(space_offer.currency, item.currency) AS currency,
           item.sellable_quantity,
           item.active AS product_active,
           item.onboarding_status,
           marketplace.business_status,
           winning_rule.id AS pricing_rule_id,
           CASE winning_rule.adjustment_type
               WHEN 'percentage' THEN (
                   (space_offer.purchase_cost_minor * (10000 + winning_rule.adjustment_value) + 5000) / 10000
               )
               WHEN 'fixed_amount' THEN space_offer.purchase_cost_minor + winning_rule.adjustment_value
               WHEN 'fixed_price' THEN winning_rule.adjustment_value
               ELSE NULL
           END AS raw_price,
           winning_rule.minimum_price_minor,
           winning_rule.maximum_price_minor
    FROM catalog_items AS item
    CROSS JOIN marketplaces AS marketplace
    LEFT JOIN suppliers AS supplier ON supplier.code = 'space'
    LEFT JOIN supplier_catalog_items AS space_offer
      ON space_offer.supplier_id = supplier.id
     AND space_offer.catalog_item_id = item.id
     AND space_offer.active
    LEFT JOIN LATERAL (
        SELECT rule.*
        FROM pricing_rules AS rule
        WHERE rule.enabled
          AND rule.retired_at IS NULL
          AND (rule.valid_from IS NULL OR rule.valid_from <= CURRENT_TIMESTAMP)
          AND (rule.valid_until IS NULL OR rule.valid_until > CURRENT_TIMESTAMP)
          AND rule.currency = COALESCE(space_offer.currency, item.currency)
          AND (
              rule.scope = 'global'
              OR (rule.scope = 'marketplace' AND rule.marketplace_id = marketplace.id)
              OR (rule.scope = 'sku' AND rule.sku = item.sku)
              OR (rule.scope = 'marketplace_sku' AND rule.marketplace_id = marketplace.id AND rule.sku = item.sku)
          )
        ORDER BY CASE rule.scope
                     WHEN 'marketplace_sku' THEN 3
                     WHEN 'sku' THEN 2
                     WHEN 'marketplace' THEN 1
                     ELSE 0
                 END DESC,
                 rule.priority DESC,
                 rule.code ASC
        LIMIT 1
    ) AS winning_rule ON TRUE
    WHERE marketplace.business_status <> 'retired'
), bounded AS (
    SELECT *,
           CASE
               WHEN raw_price IS NULL THEN NULL
               ELSE LEAST(
                   COALESCE(maximum_price_minor, raw_price),
                   GREATEST(COALESCE(minimum_price_minor, raw_price), raw_price)
               )
           END AS desired_price_minor
    FROM calculated
)
INSERT INTO marketplace_offers (
    catalog_item_id, marketplace_id, marketplace_account_id,
    desired_price_minor, currency, desired_quantity, applied_pricing_rule_id,
    source_version, status, calculated_at, created_at, updated_at
)
SELECT catalog_item_id, marketplace_id, NULL,
       desired_price_minor, currency, sellable_quantity, pricing_rule_id,
       1,
       CASE
           WHEN desired_price_minor IS NOT NULL
            AND product_active
            AND onboarding_status = 'approved'
            AND business_status IN ('pilot', 'active')
           THEN 'pending'
           ELSE 'disabled'
       END,
       CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM bounded
ON CONFLICT (catalog_item_id, marketplace_id) WHERE marketplace_account_id IS NULL
DO UPDATE SET
    desired_price_minor = EXCLUDED.desired_price_minor,
    currency = EXCLUDED.currency,
    desired_quantity = EXCLUDED.desired_quantity,
    applied_pricing_rule_id = EXCLUDED.applied_pricing_rule_id,
    source_version = marketplace_offers.source_version + 1,
    status = EXCLUDED.status,
    calculated_at = EXCLUDED.calculated_at,
    updated_at = EXCLUDED.updated_at
SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE marketplace_offers DROP COLUMN IF EXISTS calculated_at');
        $this->execute('ALTER TABLE catalog_items DROP CONSTRAINT IF EXISTS catalog_items_sellable_quantity_check');
        $this->execute(<<<'SQL'
ALTER TABLE catalog_items
    DROP COLUMN IF EXISTS offers_calculated_at,
    DROP COLUMN IF EXISTS sellable_quantity,
    ADD COLUMN sellable_quantity INTEGER GENERATED ALWAYS AS (
        GREATEST(space_available_quantity - safety_stock, 0)
    ) STORED
SQL);
    }
}
