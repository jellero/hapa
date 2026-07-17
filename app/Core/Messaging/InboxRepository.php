<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use DateTimeImmutable;

interface InboxRepository
{
    public function begin(MessageEnvelope $message, DateTimeImmutable $receivedAt): ?int;

    public function complete(string $messageId, DateTimeImmutable $processedAt): void;

    public function recordFailure(
        MessageEnvelope $message,
        int $attempt,
        DateTimeImmutable $failedAt,
        string $error,
    ): void;
}
