<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Modules\Catalog\Application\CommercialCatalogService;
use Hapa\Modules\Catalog\Contract\CatalogOfferRecalculator;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CommercialCatalogServiceTest extends TestCase
{
    private PDO $pdo;
    private CommercialCatalogService $service;
    private UserIdentity $actor;
    private ?int $catalogId = null;
    private ?int $deletedCatalogId = null;
    private ?int $marketplaceId = null;
    private ?int $productId = null;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $clock = new SystemClock();
            $this->service = new CommercialCatalogService(
                $connections,
                $clock,
                new class implements CatalogOfferRecalculator {
                    public function recalculateProduct(PDO $pdo, int $catalogItemId): int
                    {
                        return 0;
                    }

                    public function recalculateCatalog(PDO $pdo, int $commercialCatalogId): int
                    {
                        return 0;
                    }

                    public function recalculateAll(PDO $pdo): int
                    {
                        return 0;
                    }
                },
            );
            $actor = $this->pdo->query(
                "SELECT id, email, display_name, role FROM app_users WHERE status = 'active' ORDER BY created_at LIMIT 1",
            );
            $row = $actor === false ? false : $actor->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                self::markTestSkipped('Utente HAPA attivo non disponibile.');
            }
            $this->actor = new UserIdentity(
                (string) $row['id'],
                (string) $row['email'],
                (string) $row['display_name'],
                (string) $row['role'],
            );
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        $catalogId = $this->catalogId;
        if ($catalogId !== null) {
            $statement = $this->pdo->prepare(
                "DELETE FROM audit_logs WHERE entity_type = 'commercial_catalog' AND entity_id = :id",
            );
            $statement->execute(['id' => (string) $catalogId]);
            $this->delete('catalog_publication_rules', 'commercial_catalog_id', $catalogId);
            $this->delete('pricing_rules', 'commercial_catalog_id', $catalogId);
            $this->delete('commercial_catalog_marketplaces', 'commercial_catalog_id', $catalogId);
            $this->delete('commercial_catalogs', 'id', $catalogId);
        }
        $deletedCatalogId = $this->deletedCatalogId;
        if ($deletedCatalogId !== null) {
            $statement = $this->pdo->prepare(
                "DELETE FROM audit_logs WHERE entity_type = 'commercial_catalog' AND entity_id = :id",
            );
            $statement->execute(['id' => (string) $deletedCatalogId]);
        }
        $productId = $this->productId;
        if ($productId !== null) {
            $this->delete('supplier_catalog_items', 'catalog_item_id', $productId);
            $this->delete('catalog_items', 'id', $productId);
        }
        $marketplaceId = $this->marketplaceId;
        if ($marketplaceId !== null) {
            $this->delete('marketplaces', 'id', $marketplaceId);
        }
    }

    public function testAProductPassesOnlyAfterPriceAndExplicitInclusion(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $sku = 'CAT-' . strtoupper($suffix);
        $marketplaceId = $this->marketplaceId = $this->insertId(
            "INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
             VALUES (:code, 'Marketplace catalogo test', 'test', TRUE, 'active', NOW(), NOW()) RETURNING id",
            ['code' => 'catalog-test-' . $suffix],
        );
        $catalogId = $this->catalogId = $this->service->create([
            'code' => 'catalog-test-' . $suffix,
            'name' => 'Catalogo test',
            'marketplace_ids' => [$marketplaceId],
            'priority' => 100,
        ], $this->actor);
        $productId = $this->productId = $this->insertId(
            "INSERT INTO catalog_items (sku, name, currency, active, onboarding_status, created_at, updated_at)
             VALUES (:sku, 'Prodotto catalogo test', 'EUR', TRUE, 'approved', NOW(), NOW()) RETURNING id",
            ['sku' => $sku],
        );
        $supplier = $this->pdo->query("SELECT id FROM suppliers WHERE code = 'space'");
        self::assertNotFalse($supplier);
        $supplierId = (int) $supplier->fetchColumn();
        $this->execute(
            'INSERT INTO supplier_catalog_items (
                supplier_id, catalog_item_id, supplier_sku, purchase_cost_minor,
                currency, available_quantity, active, observed_at
             ) VALUES (:supplier_id, :product_id, :sku, 1000, \'EUR\', 3, TRUE, NOW())',
            ['supplier_id' => $supplierId, 'product_id' => $productId, 'sku' => $sku],
        );

        $empty = $this->service->find($catalogId);
        self::assertIsArray($empty);
        self::assertFalse($empty['ready']);
        self::assertFalse($empty['enabled']);
        self::assertSame('draft', $empty['status']);
        self::assertSame(0, $empty['eligible_product_count']);

        $this->execute(
            'INSERT INTO pricing_rules (
                commercial_catalog_id, code, name, scope, adjustment_type, adjustment_value,
                currency, enabled, version, created_at, updated_at
             ) VALUES (
                :catalog_id, :code, \'Prezzo catalogo test\', \'global\', \'percentage\', 1000,
                \'EUR\', TRUE, 1, NOW(), NOW()
             )',
            ['catalog_id' => $catalogId, 'code' => 'price-' . $suffix],
        );
        $this->execute(
            'INSERT INTO catalog_publication_rules (
                commercial_catalog_id, code, name, action, field, operator, match_value,
                priority, enabled, version, created_by, created_at, updated_at
             ) VALUES (
                :catalog_id, :code, \'Includi SKU test\', \'include\', \'sku\', \'equals\', :sku,
                100, TRUE, 1, :created_by, NOW(), NOW()
             )',
            [
                'catalog_id' => $catalogId,
                'code' => 'include-' . $suffix,
                'sku' => $sku,
                'created_by' => $this->actor->id,
            ],
        );

        $included = $this->service->find($catalogId);
        self::assertIsArray($included);
        self::assertTrue($included['ready']);
        self::assertSame(1, $included['eligible_product_count']);
        $preview = $this->service->preview($catalogId);
        self::assertCount(1, $preview);
        self::assertSame($sku, $preview[0]['sku']);
        self::assertSame([$marketplaceId], $preview[0]['marketplace_ids']);
        self::assertSame(1000, $preview[0]['purchase_cost_minor']);
        $this->service->setEnabled($catalogId, true, $this->actor, 'activate-catalog-test');
        $active = $this->service->find($catalogId);
        self::assertIsArray($active);
        self::assertTrue($active['enabled']);
        self::assertSame('active', $active['status']);

        $this->execute(
            'INSERT INTO catalog_publication_rules (
                commercial_catalog_id, code, name, action, field, operator, match_value,
                priority, enabled, version, created_by, created_at, updated_at
             ) VALUES (
                :catalog_id, :code, \'Escludi SKU test\', \'exclude\', \'sku\', \'equals\', :sku,
                10, TRUE, 1, :created_by, NOW(), NOW()
             )',
            [
                'catalog_id' => $catalogId,
                'code' => 'exclude-' . $suffix,
                'sku' => $sku,
                'created_by' => $this->actor->id,
            ],
        );

        $excluded = $this->service->find($catalogId);
        self::assertIsArray($excluded);
        self::assertSame(0, $excluded['eligible_product_count']);
        self::assertSame([], $this->service->preview($catalogId));
    }

    public function testDeleteRequiresTheExactNameAndRemovesTheCatalog(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $marketplaceId = $this->marketplaceId = $this->insertId(
            "INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
             VALUES (:code, 'Marketplace cancellazione test', 'test', TRUE, 'active', NOW(), NOW()) RETURNING id",
            ['code' => 'delete-test-' . $suffix],
        );
        $catalogName = 'Catalogo cancellazione ' . $suffix;
        $catalogId = $this->catalogId = $this->service->create([
            'code' => 'delete-test-' . $suffix,
            'name' => $catalogName,
            'marketplace_ids' => [$marketplaceId],
        ], $this->actor);

        try {
            $this->service->delete($catalogId, 'nome errato', $this->actor, 'delete-wrong-name');
            self::fail('La cancellazione deve richiedere il nome esatto.');
        } catch (InvalidArgumentException) {
            self::assertNotNull($this->service->find($catalogId));
        }

        $this->service->delete($catalogId, $catalogName, $this->actor, 'delete-catalog-test');
        $this->deletedCatalogId = $catalogId;
        $this->catalogId = null;

        self::assertNull($this->service->find($catalogId));
        $audit = $this->pdo->prepare(
            "SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'commercial_catalog' AND entity_id = :id",
        );
        $audit->execute(['id' => (string) $catalogId]);
        self::assertSame(1, (int) $audit->fetchColumn());
    }

    /** @param array<string, int|string> $parameters */
    private function insertId(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, int|string> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
    }

    private function delete(string $table, string $column, int $id): void
    {
        $statement = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s = :id', $table, $column));
        $statement->execute(['id' => $id]);
    }
}
