<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\MarketplaceOrderObservationHandler;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
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
            $this->handler = new MarketplaceOrderObservationHandler(
                $this->pdo,
                $transactions,
                new PostgresOrderRepository(
                    $this->pdo,
                    $transactions,
                    new PostgresOutboxRepository($this->pdo),
                    new OrderEventOutboxMapper(),
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
}
