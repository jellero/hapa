<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RequireLegacyCatalogReview extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
INSERT INTO audit_logs (
    actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at
)
SELECT
    NULL,
    'commercial_catalog.deactivated_for_review',
    'commercial_catalog',
    catalog.id::text,
    jsonb_build_object('enabled', TRUE, 'version', catalog.version),
    jsonb_build_object('enabled', FALSE, 'version', catalog.version + 1),
    'migration-20260730230000',
    NOW()
FROM commercial_catalogs catalog
WHERE catalog.enabled AND catalog.retired_at IS NULL
SQL);
        $this->execute(<<<'SQL'
UPDATE commercial_catalogs
SET enabled = FALSE,
    version = version + 1,
    updated_at = NOW()
WHERE enabled AND retired_at IS NULL
SQL);
    }

    public function down(): void
    {
        // Intenzionalmente irreversibile: un rollback non deve riattivare pubblicazioni senza controllo.
    }
}
