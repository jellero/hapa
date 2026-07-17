<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use DateTimeImmutable;
use Exception;
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

        if ($causationId !== null && (trim($causationId) === '' || strlen($causationId) > 200)) {
            throw new InvalidArgumentException('Causation ID envelope non valido.');
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('La versione schema dell’envelope deve essere positiva.');
        }
    }

    /** @throws JsonException */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['payload'] ?? null)) {
            throw new InvalidArgumentException('Envelope RabbitMQ non valido.');
        }

        return new self(
            self::string($data, 'message_id'),
            self::string($data, 'event_type'),
            self::integer($data, 'schema_version'),
            self::dateTime($data, 'occurred_at'),
            self::string($data, 'correlation_id'),
            array_key_exists('causation_id', $data) && $data['causation_id'] !== null
                ? self::string($data, 'causation_id')
                : null,
            $data['payload'],
        );
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

    /** @param array<string, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('%s deve essere una stringa.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('%s deve essere intero.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function dateTime(array $data, string $key): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(self::string($data, $key));
        } catch (Exception $exception) {
            throw new InvalidArgumentException(
                sprintf('%s deve essere una data valida.', $key),
                previous: $exception,
            );
        }
    }
}
