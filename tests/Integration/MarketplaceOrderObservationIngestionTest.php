<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Core\Outbox\ProviderCommandPayloadValidator;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\MarketplaceOrderObservationHandler;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
use Hapa\Modules\Procurement\Application\AutomaticSpacePurchaseGenerator;
use Hapa\Modules\Procurement\Application\SpacePurchaseGenerationService;
use Hapa\Modules\Procurement\Application\SpacePurchaseOrderResultHandler;
use Hapa\Modules\Space\Application\SpaceCatalogObservationHandler;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class MarketplaceOrderObservationIngestionTest extends TestCase
{
    private PDO $pdo;
    private MarketplaceOrderObservationHandler $handler;
    private string $accountCode;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
            $this->pdo->beginTransaction();
            $transactions = new PdoTransactionManager($this->pdo);
            $outbox = new PostgresOutboxRepository($this->pdo);
            $this->handler = new MarketplaceOrderObservationHandler(
                $this->pdo,
                $transactions,
                new PostgresOrderRepository(
                    $this->pdo,
                    $transactions,
                    $outbox,
                    new OrderEventOutboxMapper(),
                ),
                new AutomaticSpacePurchaseGenerator(
                    $this->pdo,
                    $outbox,
                    new ProviderCommandFactory(new ProviderCommandPayloadValidator(), new SystemClock()),
                ),
            );
            $this->accountCode = 'sellrapido-' . bin2hex(random_bytes(5));
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO integration_accounts (
    provider_code, code, display_name, environment, desired_status,
    configuration_version, secret_status, secret_version,
    connection_test_status, created_at, updated_at,
    automation_configuration_version
) VALUES (
    'sellrapido', :code, 'SellRapido IBS test', 'sandbox', 'pilot',
    1, 'configured', 1, 'passed', NOW(), NOW(), 1
)
SQL);
            $statement->execute(['code' => $this->accountCode]);
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

    public function testItCreatesAndUpdatesAnOrderWithoutRegressingTheSourceVersion(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $created = $this->handler->handle($this->message($suffix, 'v1', '2026-07-18T08:00:00Z'));

        self::assertSame('created', $created->outcome);
        self::assertNotNull($created->orderId);
        $row = $this->order($created->orderId);
        self::assertSame('provider-' . $suffix, $row['provider_order_id']);
        self::assertSame('IBS-' . $suffix, $row['external_order_id']);
        self::assertSame('Mario Rossi', $row['display_name']);
        self::assertSame(900, (int) $row['grand_total_minor']);
        self::assertSame(245, (int) $row['marketplace_fee_total_minor']);
        self::assertSame('Titolo venduto', $row['description_snapshot']);
        self::assertSame(2200, (int) $row['tax_rate_basis_points']);
        self::assertSame(1, $this->countBy('orders', 'provider_order_id', 'provider-' . $suffix));

        $duplicate = $this->handler->handle($this->message(
            $suffix . '-duplicate-message',
            'v1',
            '2026-07-18T08:00:00Z',
            ['provider_order_id' => 'provider-' . $suffix, 'external_order_id' => 'IBS-' . $suffix],
        ));
        self::assertSame('duplicate', $duplicate->outcome);

        $updated = $this->handler->handle($this->message(
            $suffix . '-newer',
            'v2',
            '2026-07-18T09:00:00Z',
            [
                'provider_order_id' => 'provider-' . $suffix,
                'external_order_id' => 'IBS-' . $suffix,
                'customer' => [
                    'external_customer_id' => 'buyer-' . $suffix,
                    'name' => 'Mario Rossi aggiornato',
                    'email' => 'mario@example.test',
                    'phone' => '+390000000000',
                    'fiscal_code' => null,
                    'vat_number' => null,
                ],
                'totals' => [
                    'order_minor' => 1300,
                    'shipping_minor' => 500,
                    'marketplace_fee_minor' => 300,
                    'tax_minor' => 0,
                ],
            ],
        ));
        self::assertSame('updated', $updated->outcome);
        $row = $this->order($created->orderId);
        self::assertSame('v2', $row['source_version']);
        self::assertSame('Mario Rossi aggiornato', $row['display_name']);
        self::assertSame(1300, (int) $row['grand_total_minor']);

        $stale = $this->handler->handle($this->message(
            $suffix . '-stale',
            'v0',
            '2026-07-18T07:30:00Z',
            ['provider_order_id' => 'provider-' . $suffix, 'external_order_id' => 'IBS-' . $suffix],
        ));
        self::assertSame('ignored_stale', $stale->outcome);
        self::assertSame('v2', $this->order($created->orderId)['source_version']);
    }

    public function testAnInitiallyCancelledProviderOrderIsCancelledInHapa(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $result = $this->handler->handle($this->message(
            $suffix,
            'cancelled-v1',
            '2026-07-18T08:00:00Z',
            ['provider_status' => 'cancelled'],
        ));

        self::assertSame('created', $result->outcome);
        self::assertNotNull($result->orderId);
        $order = $this->order($result->orderId);
        self::assertSame('cancelled', $order['status']);
        self::assertSame('cancelled', $order['provider_status']);
        self::assertSame(2200, (int) $order['tax_rate_basis_points']);
    }

    public function testItAutomaticallyCreatesOneIdempotentSpacePurchaseAndCommand(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $sku = 'SKU-' . $suffix;
        $ean = '1234567890123';
        $spaceAccount = 'space-' . $suffix;
        $this->enableSpacePurchases($spaceAccount);
        (new SpaceCatalogObservationHandler($this->pdo, new PdoTransactionManager($this->pdo)))->handle(
            new MessageEnvelope(
                'space-item-' . $suffix,
                'space.catalog.item.observed',
                1,
                new DateTimeImmutable('2026-07-18T06:00:00Z'),
                'space-correlation-' . $suffix,
                null,
                [
                    'supplier' => 'space',
                    'external_item_id' => 'SPACE-' . $suffix,
                    'supplier_sku' => $sku,
                    'ean' => $ean,
                    'name' => 'Prodotto acquistabile',
                    'description' => null,
                    'purchase_cost_minor' => 250,
                    'currency' => 'EUR',
                    'available_quantity' => 12,
                    'source_version' => 'space-v1-' . $suffix,
                    'observed_at' => '2026-07-18T06:00:00Z',
                ],
            ),
        );

        $created = $this->handler->handle($this->message($suffix, 'v1', '2026-07-18T08:00:00Z'));
        self::assertNotNull($created->orderId);

        $purchase = $this->pdo->prepare(<<<'SQL'
SELECT purchase.*, account.code AS account_code, line.supplier_sku,
       line.quantity, line.unit_cost_minor
FROM supplier_purchase_orders purchase
JOIN integration_accounts account ON account.id = purchase.integration_account_id
JOIN supplier_purchase_order_lines line ON line.purchase_order_id = purchase.id
WHERE purchase.order_id = :order_id AND purchase.auto_generated
SQL);
        $purchase->execute(['order_id' => $created->orderId]);
        $row = $purchase->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('requested', $row['status']);
        self::assertSame($spaceAccount, $row['account_code']);
        self::assertSame($sku, $row['supplier_sku']);
        self::assertSame(1, (int) $row['quantity']);
        self::assertSame(250, (int) $row['unit_cost_minor']);
        self::assertSame(250, (int) $row['grand_total_minor']);

        $command = $this->pdo->prepare(<<<'SQL'
SELECT payload::text FROM outbox_messages
WHERE event_type = 'space.purchase_order.submit.requested'
  AND aggregate_id = :purchase_id
SQL);
        $command->execute(['purchase_id' => (string) $row['id']]);
        $payload = json_decode((string) $command->fetchColumn(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame($spaceAccount, $payload['integration_account_code']);
        self::assertSame($sku, $payload['lines'][0]['sku']);
        self::assertSame(1, $payload['lines'][0]['quantity']);
        self::assertSame('hapa.commands', $this->value(
            "SELECT exchange_name FROM outbox_messages WHERE aggregate_id = '" . (int) $row['id'] . "' AND event_type = 'space.purchase_order.submit.requested'",
        ));

        (new SpacePurchaseOrderResultHandler($this->pdo))->handle(new MessageEnvelope(
            'space-accepted-' . $suffix,
            'space.purchase_order.accepted',
            1,
            new DateTimeImmutable('2026-07-18T08:01:00Z'),
            'correlation-' . $suffix,
            'message-' . $suffix,
            [
                'purchase_order_id' => (string) $row['id'],
                'purchase_order_version' => (int) $row['version'],
                'external_purchase_id' => 'SPACE-ORDER-' . $suffix,
                'provider_status' => 'accepted',
                'observed_at' => '2026-07-18T08:01:00Z',
            ],
        ));
        self::assertSame('accepted', $this->value(
            'SELECT status FROM supplier_purchase_orders WHERE id = ' . (int) $row['id'],
        ));

        $this->handler->handle($this->message(
            $suffix . '-updated',
            'v2',
            '2026-07-18T09:00:00Z',
            ['provider_order_id' => 'provider-' . $suffix, 'external_order_id' => 'IBS-' . $suffix],
        ));
        self::assertSame(1, (int) $this->value(
            'SELECT COUNT(*) FROM supplier_purchase_orders WHERE order_id = ' . (int) $created->orderId . ' AND auto_generated',
        ));
        self::assertSame(1, (int) $this->value(
            "SELECT COUNT(*) FROM outbox_messages WHERE aggregate_id = '" . (int) $row['id'] . "' AND event_type = 'space.purchase_order.submit.requested'",
        ));
    }

    public function testItBackfillsAnExistingOrderWhenSpaceBecomesOperational(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $sku = 'SKU-' . $suffix;
        $created = $this->handler->handle($this->message($suffix, 'v1', '2026-07-18T08:00:00Z'));
        self::assertNotNull($created->orderId);
        self::assertSame('manual_review', $this->value(
            'SELECT status FROM supplier_purchase_orders WHERE order_id = ' . $created->orderId,
        ));

        $this->enableSpacePurchases('space-backfill-' . $suffix);
        (new SpaceCatalogObservationHandler($this->pdo, new PdoTransactionManager($this->pdo)))->handle(
            new MessageEnvelope(
                'space-backfill-item-' . $suffix,
                'space.catalog.item.observed',
                1,
                new DateTimeImmutable('2026-07-18T08:30:00Z'),
                'space-backfill-correlation-' . $suffix,
                null,
                [
                    'supplier' => 'space',
                    'external_item_id' => 'SPACE-BACKFILL-' . $suffix,
                    'supplier_sku' => $sku,
                    'ean' => '1234567890123',
                    'name' => 'Prodotto Space recuperato',
                    'description' => null,
                    'purchase_cost_minor' => 275,
                    'currency' => 'EUR',
                    'available_quantity' => 10,
                    'source_version' => 'space-backfill-v1-' . $suffix,
                    'observed_at' => '2026-07-18T08:30:00Z',
                ],
            ),
        );
        $service = new SpacePurchaseGenerationService(
            new ConnectionFactory(ConfigurationLoader::load()->database),
            new ProviderCommandFactory(new ProviderCommandPayloadValidator(), new SystemClock()),
            $this->pdo,
        );

        $report = $service->generateOutstanding('backfill-' . $suffix, 10);

        self::assertSame(['examined' => 1, 'generated' => 1, 'manual_review' => 0, 'failed' => 0], $report);
        self::assertSame('requested', $this->value(
            'SELECT status FROM supplier_purchase_orders WHERE order_id = ' . $created->orderId,
        ));
        self::assertSame(1, (int) $this->value(
            "SELECT COUNT(*) FROM outbox_messages WHERE event_type = 'space.purchase_order.submit.requested' AND correlation_id LIKE 'backfill-" . $suffix . "%'",
        ));
    }

    /** @param array<string, mixed> $overrides */
    private function message(
        string $suffix,
        string $sourceVersion,
        string $modifiedAt,
        array $overrides = [],
    ): MessageEnvelope {
        $payload = [
            'integration_account_code' => $this->accountCode,
            'connector' => 'sellrapido',
            'provider_order_id' => 'provider-' . $suffix,
            'external_order_id' => 'IBS-' . $suffix,
            'marketplace_code' => 'IBS',
            'channel_code' => 'Italy',
            'provider_status' => 'accepted',
            'source_version' => $sourceVersion,
            'ordered_at' => '2026-07-18T07:00:00Z',
            'modified_at' => $modifiedAt,
            'currency' => 'EUR',
            'totals' => [
                'order_minor' => 900,
                'shipping_minor' => 500,
                'marketplace_fee_minor' => 245,
                'tax_minor' => 0,
            ],
            'customer' => [
                'external_customer_id' => 'buyer-' . preg_replace('/-.*/', '', $suffix),
                'name' => 'Mario Rossi',
                'email' => 'mario@example.test',
                'phone' => '+390000000000',
                'fiscal_code' => null,
                'vat_number' => null,
            ],
            'shipping_address' => [
                'name' => 'Mario Rossi',
                'address1' => 'Via Roma 1',
                'address2' => '',
                'postal_code' => '20100',
                'city' => 'Milano',
                'province' => 'MI',
                'country' => 'ITA',
            ],
            'rows' => [[
                'provider_row_id' => 'row-' . preg_replace('/-.*/', '', $suffix),
                'transaction_id' => 'transaction-' . $suffix,
                'external_product_id' => 'product-1',
                'sku' => 'SKU-' . preg_replace('/-.*/', '', $suffix),
                'ean' => '1234567890123',
                'title' => 'Titolo venduto',
                'quantity' => 1,
                'unit_price_minor' => 400,
                'total_price_minor' => 400,
                'shipping_minor' => 500,
                'vat_percent' => '22.00',
            ]],
            'observed_at' => (new DateTimeImmutable($modifiedAt))->modify('+5 seconds')->format(DATE_ATOM),
        ];
        foreach ($overrides as $key => $value) {
            $payload[$key] = $value;
        }

        return new MessageEnvelope(
            'message-' . $suffix,
            'marketplace.order.observed',
            1,
            new DateTimeImmutable((string) $payload['observed_at']),
            'correlation-' . $suffix,
            null,
            $payload,
        );
    }

    /** @return array<string, mixed> */
    private function order(int $orderId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT orders.*, customers.display_name, line.description_snapshot, line.tax_rate_basis_points
FROM orders
JOIN customers ON customers.id = orders.customer_id
JOIN order_lines line ON line.order_id = orders.id AND line.line_number = 1
WHERE orders.id = :id
SQL);
        $statement->execute(['id' => $orderId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function countBy(string $table, string $column, string $value): int
    {
        self::assertMatchesRegularExpression('/^[a-z_]+$/D', $table);
        self::assertMatchesRegularExpression('/^[a-z_]+$/D', $column);
        $statement = $this->pdo->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE %s = :value', $table, $column));
        $statement->execute(['value' => $value]);

        return (int) $statement->fetchColumn();
    }

    private function enableSpacePurchases(string $accountCode): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO integration_accounts (
    provider_code, code, display_name, environment, desired_status,
    configuration_version, secret_status, secret_version,
    connection_test_status, created_at, updated_at,
    automation_configuration_version
) VALUES (
    'space', :code, 'Space acquisti test', 'sandbox', 'pilot',
    1, 'configured', 1, 'passed', NOW(), NOW(), 1
)
RETURNING id
SQL);
        $statement->execute(['code' => $accountCode]);
        $accountId = $statement->fetchColumn();
        self::assertNotFalse($accountId);
        $capability = $this->pdo->prepare(<<<'SQL'
INSERT INTO integration_account_capabilities (integration_account_id, capability, enabled)
VALUES (:id, 'purchase_orders.write', TRUE)
SQL);
        $capability->execute(['id' => (int) $accountId]);
    }

    private function value(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
