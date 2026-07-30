<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCommercialCatalogs extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE commercial_catalogs (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    priority INTEGER NOT NULL DEFAULT 100,
    version INTEGER NOT NULL DEFAULT 1,
    created_by UUID NULL,
    created_at TIMESTAMPTZ NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL,
    retired_at TIMESTAMPTZ NULL,
    CONSTRAINT commercial_catalogs_identity_check CHECK (BTRIM(code) <> '' AND BTRIM(name) <> ''),
    CONSTRAINT commercial_catalogs_priority_check CHECK (priority BETWEEN 0 AND 100000),
    CONSTRAINT commercial_catalogs_version_check CHECK (version > 0)
)
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE commercial_catalog_marketplaces (
    commercial_catalog_id BIGINT NOT NULL REFERENCES commercial_catalogs (id) ON DELETE CASCADE,
    marketplace_id BIGINT NOT NULL REFERENCES marketplaces (id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (commercial_catalog_id, marketplace_id)
)
SQL);
        $this->execute('ALTER TABLE pricing_rules ADD COLUMN commercial_catalog_id BIGINT NULL');
        $this->execute('ALTER TABLE catalog_publication_rules ADD COLUMN commercial_catalog_id BIGINT NULL');
        $this->execute(<<<'SQL'
DO $$
DECLARE legacy_id BIGINT;
BEGIN
    IF EXISTS (SELECT 1 FROM pricing_rules) OR EXISTS (SELECT 1 FROM catalog_publication_rules) THEN
        INSERT INTO commercial_catalogs (code, name, description, enabled, priority, created_at, updated_at)
        VALUES ('catalogo-migrato', 'Catalogo migrato', 'Regole esistenti migrate automaticamente.', TRUE, 100, NOW(), NOW())
        RETURNING id INTO legacy_id;

        INSERT INTO commercial_catalog_marketplaces (commercial_catalog_id, marketplace_id, created_at)
        SELECT legacy_id, marketplace.id, NOW()
        FROM marketplaces marketplace
        WHERE marketplace.business_status <> 'retired'
          AND (
            EXISTS (SELECT 1 FROM pricing_rules WHERE marketplace_id IS NULL)
            OR EXISTS (SELECT 1 FROM catalog_publication_rules WHERE marketplace_id IS NULL)
            OR marketplace.id IN (SELECT marketplace_id FROM pricing_rules WHERE marketplace_id IS NOT NULL)
            OR marketplace.id IN (SELECT marketplace_id FROM catalog_publication_rules WHERE marketplace_id IS NOT NULL)
          );

        UPDATE pricing_rules SET commercial_catalog_id = legacy_id;
        UPDATE catalog_publication_rules SET commercial_catalog_id = legacy_id;
    END IF;
END $$
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE pricing_rules
    ADD CONSTRAINT pricing_rules_commercial_catalog_fk
    FOREIGN KEY (commercial_catalog_id) REFERENCES commercial_catalogs (id) ON DELETE RESTRICT
SQL);
        $this->execute(<<<'SQL'
ALTER TABLE catalog_publication_rules
    ADD CONSTRAINT publication_rules_commercial_catalog_fk
    FOREIGN KEY (commercial_catalog_id) REFERENCES commercial_catalogs (id) ON DELETE CASCADE
SQL);
        $this->execute('CREATE INDEX pricing_rules_catalog_idx ON pricing_rules (commercial_catalog_id, enabled, priority DESC)');
        $this->execute('CREATE INDEX publication_rules_catalog_idx ON catalog_publication_rules (commercial_catalog_id, action, enabled, priority)');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE catalog_publication_rules DROP CONSTRAINT IF EXISTS publication_rules_commercial_catalog_fk');
        $this->execute('ALTER TABLE pricing_rules DROP CONSTRAINT IF EXISTS pricing_rules_commercial_catalog_fk');
        $this->execute('ALTER TABLE catalog_publication_rules DROP COLUMN IF EXISTS commercial_catalog_id');
        $this->execute('ALTER TABLE pricing_rules DROP COLUMN IF EXISTS commercial_catalog_id');
        $this->execute('DROP TABLE IF EXISTS commercial_catalog_marketplaces');
        $this->execute('DROP TABLE IF EXISTS commercial_catalogs');
    }
}
