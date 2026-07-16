<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class MessageEnvelope
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $messageId,
        public string $eventType,
        public int $schemaVersion,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public ?string $causationId,
        public array $payload,
    ) {
        foreach ([$messageId, $eventType, $correlationId] as $identifier) {
            if (trim($identifier) === '' || strlen($identifier) > 200) {
                throw new InvalidArgumentException('Identificatore envelope non valido.');
            }
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('La versione schema dell’envelope deve essere positiva.');
        }
    }

    /** @throws JsonException */
    public function toJson(): string
    {
        return json_encode([
            'message_id' => $this->messageId,
            'event_type' => $this->eventType,
            'schema_version' => $this->schemaVersion,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'payload' => $this->payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
