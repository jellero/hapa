<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use DateTimeImmutable;

final readonly class ClaimedOutboxMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $id,
        public string $aggregateType,
        public string $aggregateId,
        public string $eventType,
        public array $payload,
        public string $idempotencyKey,
        public string $correlationId,
        public int $schemaVersion,
        public int $attempts,
        public int $maximumAttempts,
        public string $workerId,
        public string $lockToken,
        public DateTimeImmutable $availableAt,
    ) {
    }
}
