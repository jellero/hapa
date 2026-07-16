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

    public function testProductRegistryStoresSpacePriceAndStock(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO catalog_items (
    sku, currency, space_price_minor, space_available_quantity, safety_stock
) VALUES (
    :sku, 'EUR', 1999, 5, 3
) RETURNING space_price_minor, space_available_quantity, sellable_quantity
SQL);
        $statement->execute(['sku' => 'SKU-' . $suffix]);
        /** @var array{space_price_minor: int|string, space_available_quantity: int|string, sellable_quantity: int|string}|false $item */
        $item = $statement->fetch();

        self::assertIsArray($item);
        self::assertSame(1999, (int) $item['space_price_minor']);
        self::assertSame(5, (int) $item['space_available_quantity']);
        self::assertSame(2, (int) $item['sellable_quantity']);
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
