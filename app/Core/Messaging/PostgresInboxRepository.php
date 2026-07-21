<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use DateTimeImmutable;
use PDO;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class PostgresInboxRepository implements InboxRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function begin(MessageEnvelope $message, DateTimeImmutable $receivedAt): ?int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO inbox_messages (
    message_id, event_type, schema_version, occurred_at, correlation_id,
    causation_id, payload, status, attempts, error,
    received_at, processed_at, failed_at, updated_at
) VALUES (
    :message_id, :event_type, :schema_version, :occurred_at, :correlation_id,
    :causation_id, CAST(:payload AS JSONB), 'processing', 1, NULL,
    :received_at, NULL, NULL, :updated_at
)
ON CONFLICT (message_id) DO UPDATE
SET status = 'processing',
    attempts = inbox_messages.attempts + 1,
    error = NULL,
    processed_at = NULL,
    failed_at = NULL,
    updated_at = EXCLUDED.updated_at
WHERE inbox_messages.status = 'failed'
RETURNING attempts
SQL);
        $statement->execute([
            'message_id' => $message->messageId,
            'event_type' => $message->eventType,
            'schema_version' => $message->schemaVersion,
            'occurred_at' => self::date($message->occurredAt),
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'payload' => json_encode($message->payload, JSON_THROW_ON_ERROR),
            'received_at' => self::date($receivedAt),
            'updated_at' => self::date($receivedAt),
        ]);
        $attempt = $statement->fetchColumn();

        return $attempt === false ? null : (int) $attempt;
    }

    public function complete(string $messageId, DateTimeImmutable $processedAt): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE inbox_messages
SET status = 'processed',
    error = NULL,
    processed_at = :processed_at,
    failed_at = NULL,
    updated_at = :updated_at
WHERE message_id = :message_id
  AND status = 'processing'
SQL);
        $statement->execute([
            'message_id' => $messageId,
            'processed_at' => self::date($processedAt),
            'updated_at' => self::date($processedAt),
        ]);

        if ($statement->rowCount() !== 1) {
            throw new HapaRuntimeException(sprintf(
                'Impossibile completare il messaggio inbox %s.',
                $messageId,
            ));
        }
    }

    public function recordFailure(
        MessageEnvelope $message,
        int $attempt,
        DateTimeImmutable $failedAt,
        string $error,
    ): void {
        if ($attempt < 1) {
            throw new HapaRuntimeException('Il tentativo inbox deve essere positivo.');
        }

        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO inbox_messages (
    message_id, event_type, schema_version, occurred_at, correlation_id,
    causation_id, payload, status, attempts, error,
    received_at, processed_at, failed_at, updated_at
) VALUES (
    :message_id, :event_type, :schema_version, :occurred_at, :correlation_id,
    :causation_id, CAST(:payload AS JSONB), 'failed', :attempts, :error,
    :received_at, NULL, :failed_at, :updated_at
)
ON CONFLICT (message_id) DO UPDATE
SET status = 'failed',
    attempts = GREATEST(inbox_messages.attempts, EXCLUDED.attempts),
    error = EXCLUDED.error,
    processed_at = NULL,
    failed_at = EXCLUDED.failed_at,
    updated_at = EXCLUDED.updated_at
WHERE inbox_messages.status <> 'processed'
SQL);
        $statement->execute([
            'message_id' => $message->messageId,
            'event_type' => $message->eventType,
            'schema_version' => $message->schemaVersion,
            'occurred_at' => self::date($message->occurredAt),
            'correlation_id' => $message->correlationId,
            'causation_id' => $message->causationId,
            'payload' => json_encode($message->payload, JSON_THROW_ON_ERROR),
            'attempts' => $attempt,
            'error' => self::error($error),
            'received_at' => self::date($failedAt),
            'failed_at' => self::date($failedAt),
            'updated_at' => self::date($failedAt),
        ]);
    }

    private static function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.uP');
    }

    private static function error(string $error): string
    {
        $normalized = trim($error);

        return substr($normalized === '' ? 'Errore non specificato.' : $normalized, 0, 8000);
    }
}
