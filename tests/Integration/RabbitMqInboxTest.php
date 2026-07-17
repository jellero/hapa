<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\InboundMessageHandlerRegistry;
use Hapa\Core\Messaging\InboxConsumer;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Core\Messaging\MessageReceiver;
use Hapa\Core\Messaging\PermanentInboundFailure;
use Hapa\Core\Messaging\PostgresInboxRepository;
use Hapa\Core\Messaging\TransportProbeHandler;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class RabbitMqInboxTest extends TestCase
{
    private PDO $pdo;

    /** @var list<string> */
    private array $messageIds = [];

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        foreach ($this->messageIds as $messageId) {
            $statement = $this->pdo->prepare('DELETE FROM inbox_messages WHERE message_id = :message_id');
            $statement->execute(['message_id' => $messageId]);
        }
    }

    public function testProcessedMessagesAreDeduplicated(): void
    {
        $message = $this->probeMessage('processed');
        $repository = new PostgresInboxRepository($this->pdo);
        $receivedAt = new DateTimeImmutable('2026-07-16T20:00:01+00:00');

        self::assertSame(1, $repository->begin($message, $receivedAt));
        $repository->complete($message->messageId, $receivedAt);
        self::assertNull($repository->begin($message, $receivedAt));

        $row = $this->inboxRow($message->messageId);
        self::assertSame('processed', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNull($row['error']);
    }

    public function testFailedMessagesReceiveAMonotonicAttemptNumber(): void
    {
        $message = $this->probeMessage('retry');
        $repository = new PostgresInboxRepository($this->pdo);
        $failedAt = new DateTimeImmutable('2026-07-16T20:00:02+00:00');

        $this->pdo->beginTransaction();
        self::assertSame(1, $repository->begin($message, $failedAt));
        $this->pdo->rollBack();
        $repository->recordFailure($message, 1, $failedAt, 'temporary-1');

        $this->pdo->beginTransaction();
        self::assertSame(2, $repository->begin($message, $failedAt));
        $this->pdo->rollBack();
        $repository->recordFailure($message, 2, $failedAt, 'temporary-2');

        $row = $this->inboxRow($message->messageId);
        self::assertSame('failed', $row['status']);
        self::assertSame(2, (int) $row['attempts']);
        self::assertSame('temporary-2', $row['error']);
    }

    public function testConsumerProcessesAndThenAcknowledgesADuplicate(): void
    {
        $message = $this->probeMessage('consumer');
        $consumer = $this->consumer(
            new RepeatingMessageReceiver($message),
            new InboundMessageHandlerRegistry([new TransportProbeHandler()]),
            5,
        );

        $first = $consumer->runOnce();
        $second = $consumer->runOnce();

        self::assertTrue($first->consumed);
        self::assertTrue($first->processed);
        self::assertFalse($first->duplicate);
        self::assertTrue($second->consumed);
        self::assertFalse($second->processed);
        self::assertTrue($second->duplicate);

        $row = $this->inboxRow($message->messageId);
        self::assertSame('processed', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
    }

    public function testConsumerMakesATemporaryFailurePermanentAtTheConfiguredLimit(): void
    {
        $message = $this->message('integration.transport.transient', 'transient');
        $consumer = $this->consumer(
            new RepeatingMessageReceiver($message),
            new InboundMessageHandlerRegistry([new FailingInboundHandler()]),
            2,
        );

        try {
            $consumer->runOnce();
            self::fail('Il primo tentativo doveva fallire temporaneamente.');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(PermanentInboundFailure::class, $exception);
            self::assertSame('Errore temporaneo del test.', $exception->getMessage());
        }

        $this->expectException(PermanentInboundFailure::class);
        try {
            $consumer->runOnce();
        } finally {
            $row = $this->inboxRow($message->messageId);
            self::assertSame('failed', $row['status']);
            self::assertSame(2, (int) $row['attempts']);
            self::assertSame('Errore temporaneo del test.', $row['error']);
        }
    }

    private function consumer(
        MessageReceiver $receiver,
        InboundMessageHandlerRegistry $handlers,
        int $maximumAttempts,
    ): InboxConsumer {
        return new InboxConsumer(
            $this->pdo,
            $receiver,
            new PostgresInboxRepository($this->pdo),
            $handlers,
            new FixedInboxClock(new DateTimeImmutable('2026-07-16T20:00:03+00:00')),
            new RabbitMqConsumerConfig(
                true,
                'rabbitmq',
                5672,
                '/',
                'hapa-consumer',
                'test-password',
                'hapa.events',
                'hapa.dead',
                'hapa.inbound.events',
                'hapa.inbound.dead',
                ['integration.transport.#'],
                5.0,
                30.0,
                30,
                $maximumAttempts,
            ),
        );
    }

    private function probeMessage(string $suffix): MessageEnvelope
    {
        return $this->message('integration.transport.probe', $suffix);
    }

    private function message(string $eventType, string $suffix): MessageEnvelope
    {
        $messageId = sprintf('inbox-%s-%s', $suffix, bin2hex(random_bytes(5)));
        $this->messageIds[] = $messageId;

        return new MessageEnvelope(
            $messageId,
            $eventType,
            1,
            new DateTimeImmutable('2026-07-16T20:00:00+00:00'),
            'correlation-' . $suffix,
            null,
            ['probe_id' => 'probe-' . $suffix],
        );
    }

    /** @return array{status: string, attempts: int|string, error: ?string} */
    private function inboxRow(string $messageId): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT status, attempts, error
FROM inbox_messages
WHERE message_id = :message_id
SQL);
        $statement->execute(['message_id' => $messageId]);
        $row = $statement->fetch();
        self::assertIsArray($row);

        /** @var array{status: string, attempts: int|string, error: ?string} $row */
        return $row;
    }
}

final readonly class FixedInboxClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final readonly class RepeatingMessageReceiver implements MessageReceiver
{
    public function __construct(private MessageEnvelope $message)
    {
    }

    public function consumeOne(callable $consumer): bool
    {
        $consumer($this->message);

        return true;
    }
}

final readonly class FailingInboundHandler implements InboundMessageHandler
{
    public function eventTypes(): array
    {
        return ['integration.transport.transient'];
    }

    public function handle(MessageEnvelope $message): void
    {
        throw new RuntimeException('Errore temporaneo del test.');
    }
}
