<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExtendCatalogRuleTargets extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
ALTER TABLE pricing_rules
    ADD COLUMN match_field VARCHAR(40) NULL,
    ADD COLUMN match_operator VARCHAR(24) NULL,
    ADD COLUMN match_value VARCHAR(500) NULL
SQL);
        $this->execute(<<<'SQL'
UPDATE pricing_rules
SET match_field = 'sku',
    match_operator = 'equals',
    match_value = sku,
    scope = CASE scope WHEN 'sku' THEN 'global' ELSE 'marketplace' END,
    sku = NULL
WHERE scope IN ('sku', 'marketplace_sku')
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE pricing_rules
    ADD CONSTRAINT pricing_rules_match_check CHECK (
        (match_field IS NULL AND match_operator IS NULL AND match_value IS NULL)
        OR (
            match_field IN ('sku', 'ean', 'supplier_id', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity')
            AND match_operator IN ('equals', 'contains', 'starts_with', 'ends_with', 'minimum', 'maximum')
            AND BTRIM(match_value) <> ''
        )
    )
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE catalog_publication_rules DROP CONSTRAINT catalog_publication_rules_field_check;
ALTER TABLE catalog_publication_rules
    ADD CONSTRAINT catalog_publication_rules_field_check CHECK (
        field IN ('sku', 'ean', 'supplier_id', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity')
    )
SQL);
        $this->execute(<<<'SQL'
CREATE INDEX pricing_rules_product_match_idx
    ON pricing_rules (commercial_catalog_id, match_field, match_operator, priority DESC)
    WHERE enabled AND retired_at IS NULL
SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pricing_rules
        WHERE match_field IS NOT NULL
          AND NOT (match_field = 'sku' AND match_operator = 'equals')
    ) OR EXISTS (SELECT 1 FROM catalog_publication_rules WHERE field = 'ean') THEN
        RAISE EXCEPTION 'Rollback non sicuro: esistono regole che usano i nuovi criteri prodotto';
    END IF;
END
$$
SQL);
        $this->execute('DROP INDEX IF EXISTS pricing_rules_product_match_idx');
        $this->execute(<<<'SQL'
UPDATE pricing_rules
SET scope = CASE WHEN marketplace_id IS NULL THEN 'sku' ELSE 'marketplace_sku' END,
    sku = match_value
WHERE match_field = 'sku' AND match_operator = 'equals' AND match_value IS NOT NULL
SQL);
        $this->execute('ALTER TABLE pricing_rules DROP CONSTRAINT IF EXISTS pricing_rules_match_check');
        $this->execute(<<<'SQL'
ALTER TABLE pricing_rules
    DROP COLUMN IF EXISTS match_value,
    DROP COLUMN IF EXISTS match_operator,
    DROP COLUMN IF EXISTS match_field
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE catalog_publication_rules DROP CONSTRAINT catalog_publication_rules_field_check;
ALTER TABLE catalog_publication_rules
    ADD CONSTRAINT catalog_publication_rules_field_check CHECK (
        field IN ('sku', 'supplier_id', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity')
    )
SQL);
    }
}
