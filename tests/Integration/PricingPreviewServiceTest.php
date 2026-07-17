<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Modules\Catalog\Application\PricingPreviewService;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class PricingPreviewServiceTest extends TestCase
{
    private PDO $pdo;
    private PricingPreviewService $service;
    private int $marketplaceId;
    private int $catalogItemId;
    private int $ruleId;
    private string $marketplaceCode;
    private string $sku;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $this->service = new PricingPreviewService(
                $connections,
                new PriceCalculator(),
                new FrozenClock(new DateTimeImmutable('2026-07-17T21:00:00Z')),
            );
            $this->seed();
        } catch (Throwable $exception) {
            $this->cleanup();
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    public function testPreviewSelectsTheWinningRuleAndExplainsTechnicalReadiness(): void
    {
        $previews = $this->service->forProducts([[
            'id' => $this->catalogItemId,
            'sku' => $this->sku,
            'purchase_cost_minor' => 1000,
            'currency' => 'EUR',
            'available_quantity' => 5,
            'onboarding_status' => 'approved',
            'active' => true,
        ]]);
        $preview = current(array_filter(
            $previews[$this->catalogItemId],
            fn (array $item): bool => $item['marketplace_code'] === $this->marketplaceCode,
        ));

        self::assertIsArray($preview);
        self::assertSame(1000, $preview['base_price_minor']);
        self::assertSame(1250, $preview['selling_price_minor']);
        self::assertSame(250, $preview['markup_minor']);
        self::assertSame('preview-' . strtolower(substr($this->sku, 4)), $preview['applied_rule_code']);
        self::assertSame(1, $preview['technical_account_count']);
        self::assertSame([], $preview['blockers']);
        self::assertTrue($preview['publishable']);
    }

    public function testPreviewDoesNotDeclareAProductPublishableWithoutAnExplicitMarkupRule(): void
    {
        $this->pdo->prepare('UPDATE pricing_rules SET enabled = FALSE WHERE id = :id')->execute(['id' => $this->ruleId]);
        $previews = $this->service->forProducts([[
            'id' => $this->catalogItemId,
            'sku' => $this->sku,
            'purchase_cost_minor' => 1000,
            'currency' => 'EUR',
            'available_quantity' => 5,
            'onboarding_status' => 'approved',
            'active' => true,
        ]]);
        $preview = current(array_filter(
            $previews[$this->catalogItemId],
            fn (array $item): bool => $item['marketplace_code'] === $this->marketplaceCode,
        ));

        self::assertIsArray($preview);
        self::assertSame(1000, $preview['selling_price_minor']);
        self::assertContains('nessuna regola di ricarico applicabile', $preview['blockers']);
        self::assertFalse($preview['publishable']);
    }

    private function seed(): void
    {
        $suffix = strtolower(bin2hex(random_bytes(5)));
        $this->marketplaceCode = 'preview-' . $suffix;
        $this->sku = 'SKU-' . strtoupper($suffix);
        $this->marketplaceId = $this->insertAndReturnId(
            'INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
             VALUES (:code, :name, :adapter, TRUE, :business_status, NOW(), NOW()) RETURNING id',
            ['code' => $this->marketplaceCode, 'name' => 'Preview test', 'adapter' => 'test', 'business_status' => 'active'],
        );
        $account = $this->pdo->prepare(
            'INSERT INTO marketplace_accounts (
                marketplace_id, code, display_name, connector_code, status, technical_enabled
             ) VALUES (:marketplace_id, :code, :display_name, :connector_code, :status, TRUE)',
        );
        $account->execute([
            'marketplace_id' => $this->marketplaceId,
            'code' => 'account-' . $suffix,
            'display_name' => 'Account preview',
            'connector_code' => 'connector-' . $suffix,
            'status' => 'active',
        ]);
        $this->catalogItemId = $this->insertAndReturnId(
            'INSERT INTO catalog_items (sku, name, onboarding_status, active, currency, created_at, updated_at)
             VALUES (:sku, :name, :status, TRUE, :currency, NOW(), NOW()) RETURNING id',
            ['sku' => $this->sku, 'name' => 'Prodotto preview', 'status' => 'approved', 'currency' => 'EUR'],
        );
        $supplierStatement = $this->pdo->query("SELECT id FROM suppliers WHERE code = 'space'");
        self::assertNotFalse($supplierStatement);
        $supplierId = (int) $supplierStatement->fetchColumn();
        $offer = $this->pdo->prepare(
            'INSERT INTO supplier_catalog_items (
                supplier_id, catalog_item_id, supplier_sku, purchase_cost_minor,
                currency, available_quantity, active
             ) VALUES (:supplier_id, :catalog_item_id, :sku, 1000, :currency, 5, TRUE)',
        );
        $offer->execute(['supplier_id' => $supplierId, 'catalog_item_id' => $this->catalogItemId, 'sku' => $this->sku, 'currency' => 'EUR']);
        $this->ruleId = $this->insertAndReturnId(
            'INSERT INTO pricing_rules (
                code, name, scope, marketplace_id, adjustment_type, adjustment_value,
                currency, priority, enabled, valid_from, valid_until, created_at, updated_at
             ) VALUES (
                :code, :name, :scope, :marketplace_id, :type, 2500,
                :currency, 100, TRUE, :valid_from, :valid_until, NOW(), NOW()
             ) RETURNING id',
            [
                'code' => 'preview-' . $suffix,
                'name' => 'Preview 25%',
                'scope' => 'marketplace',
                'marketplace_id' => $this->marketplaceId,
                'type' => 'percentage',
                'currency' => 'EUR',
                'valid_from' => '2026-07-17T20:00:00Z',
                'valid_until' => '2026-07-17T22:00:00Z',
            ],
        );
    }

    private function cleanup(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        if (isset($this->ruleId)) {
            $this->delete('pricing_rules', 'id', $this->ruleId);
        }
        if (isset($this->catalogItemId)) {
            $this->delete('supplier_catalog_items', 'catalog_item_id', $this->catalogItemId);
            $this->delete('catalog_items', 'id', $this->catalogItemId);
        }
        if (isset($this->marketplaceId)) {
            $this->delete('marketplace_accounts', 'marketplace_id', $this->marketplaceId);
            $this->delete('marketplaces', 'id', $this->marketplaceId);
        }
    }

    /** @param array<string, int|string> $parameters */
    private function insertAndReturnId(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function delete(string $table, string $column, int $id): void
    {
        $statement = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s = :id', $table, $column));
        $statement->execute(['id' => $id]);
    }
}
