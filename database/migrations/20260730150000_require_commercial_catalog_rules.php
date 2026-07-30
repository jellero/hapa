<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RequireCommercialCatalogRules extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
DO $$
DECLARE fallback_id BIGINT;
BEGIN
    IF EXISTS (SELECT 1 FROM pricing_rules WHERE commercial_catalog_id IS NULL)
       OR EXISTS (SELECT 1 FROM catalog_publication_rules WHERE commercial_catalog_id IS NULL) THEN
        SELECT id INTO fallback_id FROM commercial_catalogs ORDER BY id LIMIT 1;
        IF fallback_id IS NULL THEN
            INSERT INTO commercial_catalogs (
                code, name, description, enabled, priority, version, created_at, updated_at
            ) VALUES (
                'catalogo-recuperato',
                'Catalogo recuperato',
                'Regole precedenti alla gestione dei cataloghi, assegnate automaticamente.',
                FALSE,
                100000,
                1,
                NOW(),
                NOW()
            )
            RETURNING id INTO fallback_id;
        END IF;

        UPDATE pricing_rules
        SET commercial_catalog_id = fallback_id
        WHERE commercial_catalog_id IS NULL;

        UPDATE catalog_publication_rules
        SET commercial_catalog_id = fallback_id
        WHERE commercial_catalog_id IS NULL;
    END IF;
END $$
SQL);
        $this->execute('ALTER TABLE pricing_rules ALTER COLUMN commercial_catalog_id SET NOT NULL');
        $this->execute('ALTER TABLE catalog_publication_rules ALTER COLUMN commercial_catalog_id SET NOT NULL');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE catalog_publication_rules ALTER COLUMN commercial_catalog_id DROP NOT NULL');
        $this->execute('ALTER TABLE pricing_rules ALTER COLUMN commercial_catalog_id DROP NOT NULL');
    }
}
