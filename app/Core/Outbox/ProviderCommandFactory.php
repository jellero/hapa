<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use Hapa\Core\Clock\Clock;

final readonly class ProviderCommandFactory
{
    public function __construct(
        private ProviderCommandPayloadValidator $validator,
        private Clock $clock,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function create(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        string $correlationId,
    ): OutboxMessage {
        $this->validator->validate($eventType, $payload);

        return new OutboxMessage(
            $aggregateType,
            $aggregateId,
            $eventType,
            $payload,
            (string) $payload['idempotency_key'],
            $correlationId,
            $this->clock->now(),
            schemaVersion: 2,
            exchangeName: 'hapa.commands',
            routingKey: $eventType,
        );
    }
}
