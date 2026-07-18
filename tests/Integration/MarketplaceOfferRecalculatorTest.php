<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Core\Outbox\ProviderCommandPayloadValidator;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Catalog\Application\MarketplaceOfferPublicationResultHandler;
use Hapa\Modules\Catalog\Application\MarketplaceOfferRecalculator;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class MarketplaceOfferRecalculatorTest extends TestCase
{
    private PDO $pdo;
    private MarketplaceOfferRecalculator $recalculator;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
            $this->pdo->beginTransaction();
            $clock = new SystemClock();
            $this->recalculator = new MarketplaceOfferRecalculator(
                new PriceCalculator(),
                $clock,
                new ProviderCommandFactory(new ProviderCommandPayloadValidator(), $clock),
            );
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testItPersistsHapaPriceAndSellableQuantityWithoutVersionChurn(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $sku = 'CALC-' . strtoupper($suffix);
        $marketplaceCode = 'calc-' . $suffix;
        $marketplaceId = $this->insertId(<<<'SQL'
INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
VALUES (:code, 'Marketplace calcolo', 'sellrapido', TRUE, 'pilot', NOW(), NOW())
RETURNING id
SQL, ['code' => $marketplaceCode]);
        $productId = $this->insertId(<<<'SQL'
INSERT INTO catalog_items (
    sku, name, currency, active, onboarding_status, safety_stock, version, created_at, updated_at
) VALUES (
    :sku, 'Prodotto calcolo HAPA', 'EUR', TRUE, 'approved', 2, 1, NOW(), NOW()
)
RETURNING id
SQL, ['sku' => $sku]);
        $accountId = $this->insertId(<<<'SQL'
INSERT INTO integration_accounts (
    provider_code, code, display_name, environment, desired_status,
    configuration_version, secret_status, secret_version, connection_test_status,
    automation_configuration_version, created_at, updated_at
) VALUES (
    'sellrapido', :code, 'SellRapido offerte test', 'sandbox', 'pilot',
    1, 'configured', 1, 'passed', 1, NOW(), NOW()
)
RETURNING id
SQL, ['code' => 'sellrapido-' . $suffix]);
        $this->execute(<<<'SQL'
INSERT INTO integration_account_capabilities (integration_account_id, capability, enabled)
VALUES (:account_id, 'products.write', TRUE)
SQL, ['account_id' => $accountId]);
        foreach ([
            'catalog_id' => 123456,
            'catalog_uuid' => 'catalog-' . $suffix,
            'downstream_channel' => $marketplaceCode,
            'pilot_skus' => [$sku],
        ] as $key => $value) {
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO integration_account_settings (integration_account_id, setting_key, setting_value)
VALUES (:account_id, :setting_key, CAST(:setting_value AS JSONB))
SQL);
            $statement->execute([
                'account_id' => $accountId,
                'setting_key' => $key,
                'setting_value' => json_encode($value, JSON_THROW_ON_ERROR),
            ]);
        }
        $supplier = $this->pdo->query("SELECT id FROM suppliers WHERE code = 'space'");
        self::assertNotFalse($supplier);
        $supplierId = (int) $supplier->fetchColumn();
        $this->execute(<<<'SQL'
INSERT INTO supplier_catalog_items (
    supplier_id, catalog_item_id, external_item_id, supplier_sku,
    purchase_cost_minor, currency, available_quantity, source_version,
    observed_at, active, created_at, updated_at
) VALUES (
    :supplier_id, :catalog_item_id, '987654', 'SPACE-CALC',
    1000, 'EUR', 7, 'space-v1', NOW(), TRUE, NOW(), NOW()
)
SQL, [
            'supplier_id' => $supplierId,
            'catalog_item_id' => $productId,
        ]);
        $ruleId = $this->insertId(<<<'SQL'
INSERT INTO pricing_rules (
    code, name, scope, marketplace_id, adjustment_type, adjustment_value,
    currency, priority, enabled, version, created_at, updated_at
) VALUES (
    :code, 'Ricarico 25%', 'marketplace', :marketplace_id, 'percentage', 2500,
    'EUR', 100, TRUE, 1, NOW(), NOW()
)
RETURNING id
SQL, [
            'code' => 'rule-' . $suffix,
            'marketplace_id' => $marketplaceId,
        ]);

        self::assertGreaterThanOrEqual(1, $this->recalculator->recalculateProduct($this->pdo, $productId));
        $first = $this->offer($productId, $marketplaceId);
        self::assertSame(1250, (int) $first['desired_price_minor']);
        self::assertSame(5, (int) $first['desired_quantity']);
        self::assertSame($ruleId, (int) $first['applied_pricing_rule_id']);
        self::assertSame(1, (int) $first['source_version']);
        self::assertSame('syncing', $first['status']);
        self::assertSame(5, (int) $this->value('SELECT sellable_quantity FROM catalog_items WHERE id = ' . $productId));
        $command = $this->pdo->prepare(<<<'SQL'
SELECT payload FROM outbox_messages
WHERE event_type = 'marketplace.offer.publish.requested'
  AND aggregate_id = :offer_id
ORDER BY id DESC LIMIT 1
SQL);
        $command->execute(['offer_id' => (string) $first['id']]);
        $payload = json_decode((string) $command->fetchColumn(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame($sku, $payload['sku']);
        self::assertSame(1250, $payload['price_minor']);
        self::assertSame(5, $payload['quantity']);
        self::assertSame(123456, $payload['catalog_id']);
        (new MarketplaceOfferPublicationResultHandler($this->pdo))->handle(new MessageEnvelope(
            'sellrapido-result-' . $suffix,
            'marketplace.offer.published',
            1,
            new DateTimeImmutable(),
            'correlation-result-' . $suffix,
            null,
            [
                'integration_account_code' => 'sellrapido-' . $suffix,
                'connector' => 'sellrapido',
                'offer_id' => (string) $first['id'],
                'offer_version' => 1,
                'external_offer_id' => 'remote-' . $suffix,
                'remote_version' => 'remote-v1',
            ],
        ));
        self::assertSame('synced', $this->offer($productId, $marketplaceId)['status']);

        self::assertSame(0, $this->recalculator->recalculateProduct($this->pdo, $productId));
        self::assertSame(1, (int) $this->offer($productId, $marketplaceId)['source_version']);

        $this->execute(<<<'SQL'
UPDATE supplier_catalog_items
SET purchase_cost_minor = 1200, available_quantity = 1, source_version = 'space-v2', updated_at = NOW()
WHERE catalog_item_id = :catalog_item_id
SQL, ['catalog_item_id' => $productId]);
        self::assertGreaterThanOrEqual(1, $this->recalculator->recalculateProduct($this->pdo, $productId));
        $changed = $this->offer($productId, $marketplaceId);
        self::assertSame(1500, (int) $changed['desired_price_minor']);
        self::assertSame(0, (int) $changed['desired_quantity']);
        self::assertSame(2, (int) $changed['source_version']);
        self::assertSame(0, (int) $this->value('SELECT sellable_quantity FROM catalog_items WHERE id = ' . $productId));
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

    /** @return array<string, mixed> */
    private function offer(int $productId, int $marketplaceId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, desired_price_minor, desired_quantity, applied_pricing_rule_id, source_version, status
FROM marketplace_offers
WHERE catalog_item_id = :catalog_item_id
  AND marketplace_id = :marketplace_id
  AND marketplace_account_id IS NULL
SQL);
        $statement->execute([
            'catalog_item_id' => $productId,
            'marketplace_id' => $marketplaceId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function value(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
