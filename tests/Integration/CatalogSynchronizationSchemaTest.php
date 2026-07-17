<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CatalogSynchronizationSchemaTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
            $this->pdo->beginTransaction();
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testSupplierOfferStoresSpaceCostAndAvailability(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $statement = $this->pdo->prepare(<<<'SQL'
WITH product AS (
    INSERT INTO catalog_items (sku, currency, safety_stock)
    VALUES (:sku, 'EUR', 3)
    RETURNING id
), supplier AS (
    SELECT id FROM suppliers WHERE code = 'space'
)
INSERT INTO supplier_catalog_items (
    supplier_id, catalog_item_id, external_item_id, purchase_cost_minor,
    currency, available_quantity, source_version, observed_at
)
SELECT supplier.id, product.id, :external_item_id, 1999,
       'EUR', 5, :source_version, NOW()
FROM supplier, product
RETURNING purchase_cost_minor, available_quantity
SQL);
        $statement->execute([
            'sku' => 'SKU-' . $suffix,
            'external_item_id' => 'SPACE-' . $suffix,
            'source_version' => 'version-' . $suffix,
        ]);
        /** @var array{purchase_cost_minor: int|string, available_quantity: int|string}|false $item */
        $item = $statement->fetch();

        self::assertIsArray($item);
        self::assertSame(1999, (int) $item['purchase_cost_minor']);
        self::assertSame(5, (int) $item['available_quantity']);
    }

    public function testPricingRuleScopeMustMatchItsTargets(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $marketplaceId = $this->marketplace($suffix);
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO pricing_rules (
    code, name, scope, marketplace_id, adjustment_type, adjustment_value, currency
) VALUES (
    :code, 'Regola non valida', 'global', :marketplace_id, 'percentage', 1500, 'EUR'
)
SQL);

        $this->expectException(PDOException::class);
        $statement->execute([
            'code' => 'bad-' . $suffix,
            'marketplace_id' => $marketplaceId,
        ]);
    }

    public function testAutomationSchedulerIsNotStoredInHapaDatabase(): void
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM information_schema.tables
WHERE table_schema = 'public' AND table_name = 'automation_jobs'
SQL);

        self::assertNotFalse($statement);
        self::assertSame(0, (int) $statement->fetchColumn());
    }

    private function marketplace(string $suffix): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO marketplaces (code, name, adapter_key, active, created_at, updated_at)
VALUES (:code, 'Test Marketplace', 'test', TRUE, NOW(), NOW())
RETURNING id
SQL);
        $statement->execute(['code' => 'catalog-' . $suffix]);

        return (int) $statement->fetchColumn();
    }
}
