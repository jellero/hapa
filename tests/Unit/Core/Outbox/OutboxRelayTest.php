<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Outbox;

use DateTimeImmutable;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Configuration\OutboxRelayConfig;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Core\Messaging\MessagePublisher;
use Hapa\Core\Outbox\ClaimedOutboxMessage;
use Hapa\Core\Outbox\OutboxEnvelopeFactory;
use Hapa\Core\Outbox\OutboxMessage;
use Hapa\Core\Outbox\OutboxRelay;
use Hapa\Core\Outbox\OutboxRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OutboxRelayTest extends TestCase
{
    public function testItPublishesAndCompletesClaimedMessages(): void
    {
        $repository = new RelayRepositoryFake([$this->message(1, 10)]);
        $publisher = new RelayPublisherFake();
        $relay = $this->relay($repository, $publisher);

        $report = $relay->runOnce();

        self::assertSame(1, $report->claimed);
        self::assertSame(1, $report->published);
        self::assertCount(1, $repository->completed);
        self::assertSame('hapa.events', $publisher->exchangeNames[0]);
        self::assertSame('order.changed', $publisher->routingKeys[0]);
        self::assertSame('order.changed', $publisher->messages[0]->eventType);
    }

    public function testItSchedulesRetryAfterATemporaryPublicationFailure(): void
    {
        $repository = new RelayRepositoryFake([$this->message(2, 10)]);
        $publisher = new RelayPublisherFake(true);
        $relay = $this->relay($repository, $publisher);

        $report = $relay->runOnce();

        self::assertSame(1, $report->retried);
        self::assertCount(1, $repository->retried);
        self::assertSame('2026-07-16T10:01:00+00:00', $repository->retried[0]['available_at']->format(DATE_ATOM));
        self::assertSame('RabbitMQ non disponibile.', $repository->retried[0]['error']);
    }

    public function testItMarksTheMessageDeadAtTheMaximumAttempt(): void
    {
        $repository = new RelayRepositoryFake([$this->message(3, 3)]);
        $publisher = new RelayPublisherFake(true);
        $relay = $this->relay($repository, $publisher);

        $report = $relay->runOnce();

        self::assertSame(1, $report->dead);
        self::assertCount(1, $repository->deadMessages);
        self::assertSame('RabbitMQ non disponibile.', $repository->deadMessages[0]['error']);
    }

    private function relay(RelayRepositoryFake $repository, RelayPublisherFake $publisher): OutboxRelay
    {
        return new OutboxRelay(
            $repository,
            $publisher,
            new OutboxEnvelopeFactory(),
            new FixedRelayClock(new DateTimeImmutable('2026-07-16T10:00:00+00:00')),
            new OutboxRelayConfig('test-relay', 50, 300, 30, 3600),
        );
    }

    private function message(int $attempts, int $maximumAttempts): ClaimedOutboxMessage
    {
        return new ClaimedOutboxMessage(
            1,
            '4487f2ea-d4d4-8b1d-b9c8-dceacfc7ca8a',
            'order',
            'ORD-001',
            'order.changed',
            [
                'order_number' => 'ORD-001',
                'version' => 1,
                'change_type' => 'order.created',
                'status' => 'imported',
                'occurred_at' => '2026-07-16T09:59:00+00:00',
            ],
            'order:ORD-001:v1:order.created',
            'order-ORD-001-v1',
            1,
            $attempts,
            $maximumAttempts,
            'test-relay',
            'lock-token',
            new DateTimeImmutable('2026-07-16T09:59:00+00:00'),
            new DateTimeImmutable('2026-07-16T09:59:00+00:00'),
        );
    }
}

final class FixedRelayClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final class RelayPublisherFake implements MessagePublisher
{
    /** @var list<string> */
    public array $exchangeNames = [];

    /** @var list<string> */
    public array $routingKeys = [];

    /** @var list<MessageEnvelope> */
    public array $messages = [];

    public function __construct(private bool $fail = false)
    {
    }

    public function publish(string $exchangeName, string $routingKey, MessageEnvelope $message): void
    {
        if ($this->fail) {
            throw new RuntimeException('RabbitMQ non disponibile.');
        }

        $this->exchangeNames[] = $exchangeName;
        $this->routingKeys[] = $routingKey;
        $this->messages[] = $message;
    }
}

final class RelayRepositoryFake implements OutboxRepository
{
    /** @var list<ClaimedOutboxMessage> */
    public array $completed = [];

    /** @var list<array{message: ClaimedOutboxMessage, available_at: DateTimeImmutable, error: string}> */
    public array $retried = [];

    /** @var list<array{message: ClaimedOutboxMessage, failed_at: DateTimeImmutable, error: string}> */
    public array $deadMessages = [];

    /** @param list<ClaimedOutboxMessage> $messages */
    public function __construct(private array $messages)
    {
    }

    public function append(OutboxMessage $message): bool
    {
        return true;
    }

    public function claim(string $workerId, int $limit, DateTimeImmutable $now): array
    {
        return $this->messages;
    }

    public function complete(ClaimedOutboxMessage $message, DateTimeImmutable $completedAt): void
    {
        $this->completed[] = $message;
    }

    public function retry(ClaimedOutboxMessage $message, DateTimeImmutable $availableAt, string $error): void
    {
        $this->retried[] = [
            'message' => $message,
            'available_at' => $availableAt,
            'error' => $error,
        ];
    }

    public function dead(ClaimedOutboxMessage $message, DateTimeImmutable $failedAt, string $error): void
    {
        $this->deadMessages[] = [
            'message' => $message,
            'failed_at' => $failedAt,
            'error' => $error,
        ];
    }

    public function recoverExpired(DateTimeImmutable $expiredBefore, DateTimeImmutable $availableAt): int
    {
        return 0;
    }
}
