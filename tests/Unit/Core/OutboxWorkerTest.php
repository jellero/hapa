<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Closure;
use DateTimeImmutable;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Outbox\ClaimedOutboxMessage;
use Hapa\Core\Outbox\OutboxHandlerRegistry;
use Hapa\Core\Outbox\OutboxMessage;
use Hapa\Core\Outbox\OutboxMessageHandler;
use Hapa\Core\Outbox\OutboxRepository;
use Hapa\Core\Outbox\OutboxWorker;
use Hapa\Core\Outbox\PermanentProcessingFailure;
use Hapa\Core\Outbox\RetryBackoff;
use Hapa\Core\Outbox\TemporaryProcessingFailure;
use PHPUnit\Framework\TestCase;

final class OutboxWorkerTest extends TestCase
{
    public function testItCompletesAClaimedMessage(): void
    {
        $repository = new InMemoryOutboxRepository([$this->message()]);
        $worker = $this->worker($repository, static function (): void {
        });

        $report = $worker->runOnce('worker-1', 10);

        self::assertSame(1, $report->claimed);
        self::assertSame(1, $report->completed);
        self::assertSame([1], $repository->completed);
    }

    public function testItSchedulesATemporaryFailureWithExplicitRetryAfter(): void
    {
        $repository = new InMemoryOutboxRepository([$this->message(attempts: 2)]);
        $worker = $this->worker($repository, static function (): void {
            throw new TemporaryProcessingFailure('Provider non disponibile', 120);
        });

        $report = $worker->runOnce('worker-1', 10);

        self::assertSame(1, $report->retried);
        self::assertSame('2026-07-16T10:02:00+00:00', $repository->retryAt?->format(DATE_ATOM));
    }

    public function testItMovesPermanentAndExhaustedFailuresToDeadLetter(): void
    {
        $permanentRepository = new InMemoryOutboxRepository([$this->message()]);
        $this->worker($permanentRepository, static function (): void {
            throw new PermanentProcessingFailure('Payload rifiutato');
        })->runOnce('worker-1', 10);

        $exhaustedRepository = new InMemoryOutboxRepository([$this->message(attempts: 10)]);
        $this->worker($exhaustedRepository, static function (): void {
            throw new TemporaryProcessingFailure('Timeout');
        })->runOnce('worker-2', 10);

        self::assertSame([1], $permanentRepository->dead);
        self::assertSame([1], $exhaustedRepository->dead);
    }

    public function testItRecoversExpiredLocksBeforeClaiming(): void
    {
        $repository = new InMemoryOutboxRepository([]);
        $repository->recovered = 3;

        $report = $this->worker($repository, static function (): void {
        })->runOnce('worker-1', 10);

        self::assertSame(3, $report->recovered);
        self::assertSame('2026-07-16T09:55:00+00:00', $repository->expiredBefore?->format(DATE_ATOM));
    }

    private function worker(InMemoryOutboxRepository $repository, Closure $callback): OutboxWorker
    {
        return new OutboxWorker(
            $repository,
            new OutboxHandlerRegistry([new CallbackOutboxHandler($callback)]),
            new RetryBackoff(30, 3600),
            new FrozenClock(new DateTimeImmutable('2026-07-16T10:00:00+00:00')),
            300,
        );
    }

    private function message(int $attempts = 1): ClaimedOutboxMessage
    {
        return new ClaimedOutboxMessage(
            1,
            'order',
            'ORD-001',
            'test.event',
            ['value' => 1],
            'test-key',
            'test-correlation',
            1,
            $attempts,
            10,
            'worker-1',
            'lock-token',
            new DateTimeImmutable('2026-07-16T09:59:00+00:00'),
        );
    }
}

final class CallbackOutboxHandler implements OutboxMessageHandler
{
    public function __construct(private readonly Closure $callback)
    {
    }

    public function eventTypes(): array
    {
        return ['test.event'];
    }

    public function handle(ClaimedOutboxMessage $message): void
    {
        ($this->callback)($message);
    }
}

final class InMemoryOutboxRepository implements OutboxRepository
{
    /** @var list<int> */
    public array $completed = [];

    /** @var list<int> */
    public array $dead = [];

    public ?DateTimeImmutable $retryAt = null;
    public ?DateTimeImmutable $expiredBefore = null;
    public int $recovered = 0;

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
        return array_slice($this->messages, 0, $limit);
    }

    public function complete(ClaimedOutboxMessage $message, DateTimeImmutable $completedAt): void
    {
        $this->completed[] = $message->id;
    }

    public function retry(ClaimedOutboxMessage $message, DateTimeImmutable $availableAt, string $error): void
    {
        $this->retryAt = $availableAt;
    }

    public function dead(ClaimedOutboxMessage $message, DateTimeImmutable $failedAt, string $error): void
    {
        $this->dead[] = $message->id;
    }

    public function recoverExpired(DateTimeImmutable $expiredBefore, DateTimeImmutable $availableAt): int
    {
        $this->expiredBefore = $expiredBefore;

        return $this->recovered;
    }
}
