<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Domain\Order;
use Hapa\Modules\Orders\Domain\OrderLine;
use Hapa\Modules\Orders\Domain\OrderNumber;
use Hapa\Modules\Orders\Domain\OrderStatus;
use Hapa\Modules\Orders\Domain\StaleOrderVersion;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class OrderTransactionalOutboxTest extends TestCase
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

    public function testOrderAndEventsArePersistedAtomicallyForExternalDelivery(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $marketplaceId = $this->createMarketplace($suffix);
        $repository = new PostgresOrderRepository(
            $this->pdo,
            new PdoTransactionManager($this->pdo),
            new PostgresOutboxRepository($this->pdo),
            new OrderEventOutboxMapper(),
        );
        $number = new OrderNumber('ORD-' . strtoupper($suffix));
        $order = Order::marketplace(
            $number,
            $marketplaceId,
            'external-' . $suffix,
            'EUR',
            new DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            new OrderLine(1, 'SKU-' . $suffix, 'line-1', null, 2),
        );

        $repository->save($order, 0);
        self::assertSame([], $order->pendingEvents());

        $loaded = $repository->find($number);
        self::assertNotNull($loaded);
        self::assertSame(OrderStatus::Imported, $loaded->status());
        self::assertSame(1, $loaded->version());

        $loaded->accept(new DateTimeImmutable('2026-07-16T10:01:00+00:00'));
        $repository->save($loaded, 1);

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT event_type, schema_version, correlation_id, status
FROM outbox_messages
WHERE aggregate_id = :order_number
ORDER BY id
SQL);
        $statement->execute(['order_number' => (string) $number]);
        $messages = $statement->fetchAll();

        self::assertCount(2, $messages);
        self::assertSame('pending', $messages[0]['status']);
        self::assertSame('pending', $messages[1]['status']);
        self::assertSame(1, (int) $messages[0]['schema_version']);
        self::assertNotSame('', (string) $messages[0]['correlation_id']);

        $this->expectException(StaleOrderVersion::class);
        $repository->save($loaded, 1);
    }

    private function createMarketplace(string $suffix): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO marketplaces (code, name, adapter_key, active, created_at, updated_at)
VALUES (:code, :name, :adapter, TRUE, NOW(), NOW())
RETURNING id
SQL);
        $statement->execute([
            'code' => 'outbox-' . $suffix,
            'name' => 'Transactional outbox test',
            'adapter' => 'test',
        ]);
        $id = $statement->fetchColumn();
        self::assertNotFalse($id);

        return (int) $id;
    }
}
