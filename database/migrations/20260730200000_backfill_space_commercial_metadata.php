<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class BackfillSpaceCommercialMetadata extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
UPDATE supplier_catalog_items
SET artist = COALESCE(NULLIF(BTRIM(source_attributes->>'discoteca_artista'), ''), artist),
    title = COALESCE(NULLIF(BTRIM(source_attributes->>'discoteca_titolo'), ''), title),
    label = COALESCE(NULLIF(BTRIM(source_attributes->>'discoteca_etichetta'), ''), label),
    format = COALESCE(NULLIF(BTRIM(source_attributes->>'discoteca_formato'), ''), format),
    product_url = COALESCE(
        NULLIF(BTRIM(source_attributes->>'url_pagina'), ''),
        NULLIF(BTRIM(source_attributes->>'url'), ''),
        product_url
    ),
    image_url = COALESCE(
        NULLIF(BTRIM(source_attributes->>'url_immagine'), ''),
        NULLIF(BTRIM(source_attributes->>'url_img'), ''),
        image_url
    ),
    updated_at = NOW()
WHERE source_attributes <> '{}'::jsonb
SQL);
        $this->execute(<<<'SQL'
UPDATE catalog_items item
SET name = LEFT(
        CONCAT_WS(
            ' - ',
            NULLIF(BTRIM(source.artist), ''),
            NULLIF(BTRIM(source.title), '')
        ),
        255
    ),
    updated_at = NOW()
FROM supplier_catalog_items source
JOIN suppliers supplier ON supplier.id = source.supplier_id AND supplier.code = 'space'
WHERE source.catalog_item_id = item.id
  AND item.onboarding_status = 'pending_review'
  AND (NULLIF(BTRIM(source.artist), '') IS NOT NULL OR NULLIF(BTRIM(source.title), '') IS NOT NULL)
SQL);
    }

    public function down(): void
    {
        // Backfill non distruttivo: i valori sorgente precedenti restano nel JSON tecnico.
    }
}
