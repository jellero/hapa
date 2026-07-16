<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use DateTimeImmutable;
use Hapa\Core\Messaging\DeterministicMessageId;
use Hapa\Core\Messaging\MessageEnvelope;
use Throwable;

final class OutboxEnvelopeFactory
{
    public function create(ClaimedOutboxMessage $message): MessageEnvelope
    {
        return new MessageEnvelope(
            DeterministicMessageId::fromIdempotencyKey($message->idempotencyKey),
            $message->eventType,
            $message->schemaVersion,
            $this->occurredAt($message),
            $message->correlationId,
            null,
            $message->payload,
        );
    }

    private function occurredAt(ClaimedOutboxMessage $message): DateTimeImmutable
    {
        $value = $message->payload['occurred_at'] ?? null;
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable) {
            }
        }

        return $message->createdAt;
    }
}
