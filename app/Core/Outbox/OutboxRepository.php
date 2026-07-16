<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use DateTimeImmutable;

interface OutboxRepository
{
    public function append(OutboxMessage $message): bool;

    /** @return list<ClaimedOutboxMessage> */
    public function claim(string $workerId, int $limit, DateTimeImmutable $now): array;

    public function complete(ClaimedOutboxMessage $message, DateTimeImmutable $completedAt): void;

    public function retry(
        ClaimedOutboxMessage $message,
        DateTimeImmutable $availableAt,
        string $error,
    ): void;

    public function dead(ClaimedOutboxMessage $message, DateTimeImmutable $failedAt, string $error): void;

    public function recoverExpired(DateTimeImmutable $expiredBefore, DateTimeImmutable $availableAt): int;
}
