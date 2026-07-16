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

    public function testSellableQuantitySubtractsSafetyStockAndNeverBecomesNegative(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO catalog_items (
    sku, currency, space_price_minor, space_available_quantity, safety_stock
) VALUES (
    :sku, 'EUR', 1999, 5, 3
) RETURNING id, sellable_quantity
SQL);
        $statement->execute(['sku' => 'SKU-' . $suffix]);
        /** @var array{id: int|string, sellable_quantity: int|string}|false $item */
        $item = $statement->fetch();
        self::assertIsArray($item);
        self::assertSame(2, (int) $item['sellable_quantity']);

        $update = $this->pdo->prepare(
            'UPDATE catalog_items SET space_available_quantity = 1 WHERE id = :id RETURNING sellable_quantity',
        );
        $update->execute(['id' => $item['id']]);
        self::assertSame(0, (int) $update->fetchColumn());
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

    public function testCatalogAutomationJobsRemainDisabledUntilAdaptersAreVerified(): void
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT code, enabled
FROM automation_jobs
WHERE code IN ('sync_space_catalog', 'publish_marketplace_offers')
ORDER BY code
SQL);
        self::assertNotFalse($statement);
        /** @var list<array{code: string, enabled: bool|string}> $jobs */
        $jobs = $statement->fetchAll();

        self::assertSame(['publish_marketplace_offers', 'sync_space_catalog'], array_column($jobs, 'code'));
        foreach ($jobs as $job) {
            self::assertContains($job['enabled'], [false, '0', 'f']);
        }
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
