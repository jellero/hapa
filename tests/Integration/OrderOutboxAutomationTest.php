<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Outbox\OutboxHandlerRegistry;
use Hapa\Core\Outbox\OutboxWorker;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Outbox\RetryBackoff;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Domain\Order;
use Hapa\Modules\Orders\Domain\OrderLine;
use Hapa\Modules\Orders\Domain\OrderNumber;
use Hapa\Modules\Orders\Domain\OrderStatus;
use Hapa\Modules\Orders\Domain\StaleOrderVersion;
use Hapa\Modules\Orders\Infrastructure\Automation\OrderAuditOutboxHandler;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class OrderOutboxAutomationTest extends TestCase
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

    public function testOrderAndOutboxArePersistedThenProcessedIdempotently(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $marketplaceId = $this->createMarketplace($suffix);
        $outbox = new PostgresOutboxRepository($this->pdo);
        $repository = new PostgresOrderRepository(
            $this->pdo,
            new PdoTransactionManager($this->pdo),
            $outbox,
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

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM outbox_messages WHERE aggregate_id = :order_number');
        $statement->execute(['order_number' => (string) $number]);
        self::assertSame(2, (int) $statement->fetchColumn());

        $clock = new FrozenClock(new DateTimeImmutable('2026-07-16T10:02:00+00:00'));
        $worker = new OutboxWorker(
            $outbox,
            new OutboxHandlerRegistry([new OrderAuditOutboxHandler($this->pdo, $clock)]),
            new RetryBackoff(30, 3600),
            $clock,
            300,
        );
        $report = $worker->runOnce('integration-worker', 10);

        self::assertSame(2, $report->completed);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM audit_logs WHERE entity_id = :order_number');
        $statement->execute(['order_number' => (string) $number]);
        self::assertSame(2, (int) $statement->fetchColumn());

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
            'code' => 'automation-' . $suffix,
            'name' => 'Automation test',
            'adapter' => 'test',
        ]);
        $id = $statement->fetchColumn();
        self::assertNotFalse($id);

        return (int) $id;
    }
}
