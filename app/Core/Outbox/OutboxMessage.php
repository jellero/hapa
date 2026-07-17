<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class OutboxMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $aggregateType,
        public string $aggregateId,
        public string $eventType,
        public array $payload,
        public string $idempotencyKey,
        public string $correlationId,
        public DateTimeImmutable $availableAt,
        public int $schemaVersion = 1,
        public int $maximumAttempts = 10,
        public string $exchangeName = 'hapa.events',
        public ?string $routingKey = null,
    ) {
        foreach ([
            'tipo aggregato' => $aggregateType,
            'ID aggregato' => $aggregateId,
            'tipo evento' => $eventType,
            'chiave di idempotenza' => $idempotencyKey,
            'correlation ID' => $correlationId,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo %s del messaggio outbox è obbligatorio.', $field));
            }
        }

        if ($schemaVersion < 1 || $maximumAttempts < 1) {
            throw new InvalidArgumentException('Versione schema e numero massimo di tentativi devono essere positivi.');
        }

        if (!in_array($exchangeName, ['hapa.events', 'hapa.commands'], true)) {
            throw new InvalidArgumentException('Exchange outbox non supportato.');
        }

        if ($routingKey !== null && trim($routingKey) === '') {
            throw new InvalidArgumentException('La routing key outbox non può essere vuota.');
        }
    }
}
