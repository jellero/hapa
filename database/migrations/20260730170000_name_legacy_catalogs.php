<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NameLegacyCatalogs extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
WITH legacy_target AS (
    SELECT link.commercial_catalog_id,
           COUNT(*) AS marketplace_count,
           MIN(marketplace.name) AS marketplace_name,
           MIN(marketplace.code) AS marketplace_code
    FROM commercial_catalog_marketplaces link
    JOIN marketplaces marketplace ON marketplace.id = link.marketplace_id
    JOIN commercial_catalogs catalog ON catalog.id = link.commercial_catalog_id
    WHERE catalog.code = 'catalogo-migrato'
    GROUP BY link.commercial_catalog_id
)
UPDATE commercial_catalogs catalog
SET name = CASE
        WHEN target.marketplace_count = 1 THEN 'Catalogo ' || target.marketplace_name
        ELSE 'Catalogo marketplace esistente'
    END,
    code = CASE
        WHEN target.marketplace_count = 1
         AND NOT EXISTS (
             SELECT 1 FROM commercial_catalogs conflict
             WHERE conflict.id <> catalog.id
               AND conflict.code = 'catalogo-' || target.marketplace_code
         )
        THEN 'catalogo-' || target.marketplace_code
        ELSE catalog.code
    END,
    description = CASE
        WHEN target.marketplace_count = 1 THEN 'Configurazione commerciale esistente per ' || target.marketplace_name || '.'
        ELSE 'Configurazione commerciale esistente migrata automaticamente.'
    END,
    updated_at = NOW()
FROM legacy_target target
WHERE catalog.id = target.commercial_catalog_id
SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
UPDATE commercial_catalogs
SET code = 'catalogo-migrato',
    name = 'Catalogo migrato',
    description = 'Regole esistenti migrate automaticamente.',
    updated_at = NOW()
WHERE code LIKE 'catalogo-%'
  AND description LIKE 'Configurazione commerciale esistente%'
SQL);
    }
}
