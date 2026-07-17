<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use DateTimeImmutable;
use JsonException;
use PDO;
use RuntimeException;

final readonly class PostgresOutboxRepository implements OutboxRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function append(OutboxMessage $message): bool
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO outbox_messages (
    aggregate_type, aggregate_id, event_type, exchange_name, routing_key, payload, status,
    idempotency_key, correlation_id, schema_version, attempts, max_attempts,
    available_at, created_at, updated_at
) VALUES (
    :aggregate_type, :aggregate_id, :event_type, :exchange_name, :routing_key, CAST(:payload AS JSONB), 'pending',
    :idempotency_key, :correlation_id, :schema_version, 0, :max_attempts,
    :available_at, :created_at, :updated_at
)
ON CONFLICT (idempotency_key) DO NOTHING
SQL);
        $statement->execute([
            'aggregate_type' => $message->aggregateType,
            'aggregate_id' => $message->aggregateId,
            'event_type' => $message->eventType,
            'exchange_name' => $message->exchangeName,
            'routing_key' => $message->routingKey ?? $message->eventType,
            'payload' => json_encode($message->payload, JSON_THROW_ON_ERROR),
            'idempotency_key' => $message->idempotencyKey,
            'correlation_id' => $message->correlationId,
            'schema_version' => $message->schemaVersion,
            'max_attempts' => $message->maximumAttempts,
            'available_at' => self::date($message->availableAt),
            'created_at' => self::date($message->availableAt),
            'updated_at' => self::date($message->availableAt),
        ]);

        return $statement->rowCount() === 1;
    }

    public function claim(string $workerId, int $limit, DateTimeImmutable $now): array
    {
        if (trim($workerId) === '' || $limit < 1 || $limit > 500) {
            throw new RuntimeException('Worker identity e limite di claim devono essere validi.');
        }

        $lockToken = bin2hex(random_bytes(16));
        $statement = $this->pdo->prepare(<<<'SQL'
WITH candidates AS (
    SELECT id
    FROM outbox_messages
    WHERE status IN ('pending', 'retry')
      AND available_at <= :available_at
      AND attempts < max_attempts
    ORDER BY available_at, id
    FOR UPDATE SKIP LOCKED
    LIMIT :batch_limit
)
UPDATE outbox_messages AS message
SET status = 'processing',
    attempts = message.attempts + 1,
    locked_at = :locked_at,
    locked_by = :locked_by,
    lock_token = :lock_token,
    updated_at = :updated_at
FROM candidates
WHERE message.id = candidates.id
RETURNING message.id, message.aggregate_type, message.aggregate_id, message.event_type,
          message.exchange_name, message.routing_key, message.payload::text AS payload,
          message.idempotency_key, message.correlation_id,
          message.schema_version, message.attempts, message.max_attempts,
          message.available_at, message.created_at, message.locked_by, message.lock_token
SQL);
        $statement->bindValue('available_at', self::date($now));
        $statement->bindValue('batch_limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('locked_at', self::date($now));
        $statement->bindValue('updated_at', self::date($now));
        $statement->bindValue('locked_by', $workerId);
        $statement->bindValue('lock_token', $lockToken);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(self::hydrate(...), $rows);
    }

    public function complete(ClaimedOutboxMessage $message, DateTimeImmutable $completedAt): void
    {
        $this->finish(
            $message,
            "status = 'completed', completed_at = :result_at, failed_at = NULL, last_error = NULL",
            $completedAt,
            [],
        );
    }

    public function retry(
        ClaimedOutboxMessage $message,
        DateTimeImmutable $availableAt,
        string $error,
    ): void {
        $this->finish(
            $message,
            "status = 'retry', available_at = :result_at, completed_at = NULL, failed_at = NULL, last_error = :last_error",
            $availableAt,
            [
                'last_error' => self::error($error),
            ],
        );
    }

    public function dead(ClaimedOutboxMessage $message, DateTimeImmutable $failedAt, string $error): void
    {
        $this->finish(
            $message,
            "status = 'dead', completed_at = NULL, failed_at = :result_at, last_error = :last_error",
            $failedAt,
            ['last_error' => self::error($error)],
        );
    }

    public function recoverExpired(DateTimeImmutable $expiredBefore, DateTimeImmutable $availableAt): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE outbox_messages
SET status = CASE WHEN attempts >= max_attempts THEN 'dead' ELSE 'retry' END,
    available_at = CASE
        WHEN attempts >= max_attempts THEN available_at
        ELSE CAST(:retry_at AS TIMESTAMPTZ)
    END,
    failed_at = CASE
        WHEN attempts >= max_attempts THEN CAST(:failed_at AS TIMESTAMPTZ)
        ELSE NULL
    END,
    last_error = 'Lock worker scaduto: messaggio recuperato automaticamente.',
    locked_at = NULL,
    locked_by = NULL,
    lock_token = NULL,
    updated_at = :updated_at
WHERE status = 'processing'
  AND locked_at < :expired_before
SQL);
        $statement->execute([
            'retry_at' => self::date($availableAt),
            'failed_at' => self::date($availableAt),
            'updated_at' => self::date($availableAt),
            'expired_before' => self::date($expiredBefore),
        ]);

        return $statement->rowCount();
    }

    /**
     * @param array<string, string> $parameters
     */
    private function finish(
        ClaimedOutboxMessage $message,
        string $changes,
        DateTimeImmutable $finishedAt,
        array $parameters,
    ): void {
        $statement = $this->pdo->prepare(sprintf(<<<'SQL'
UPDATE outbox_messages
SET %s,
    locked_at = NULL,
    locked_by = NULL,
    lock_token = NULL,
    updated_at = :updated_at
WHERE id = :id
  AND status = 'processing'
  AND locked_by = :locked_by
  AND lock_token = :lock_token
SQL, $changes));
        $statement->execute([
            'result_at' => self::date($finishedAt),
            'updated_at' => self::date($finishedAt),
            'id' => $message->id,
            'locked_by' => $message->workerId,
            'lock_token' => $message->lockToken,
            ...$parameters,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new LostOutboxLock($message->id);
        }
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): ClaimedOutboxMessage
    {
        try {
            $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Payload outbox JSON non decodificabile.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Il payload outbox deve essere un oggetto JSON.');
        }

        /** @var array<string, mixed> $payload */
        return new ClaimedOutboxMessage(
            (int) $row['id'],
            (string) $row['aggregate_type'],
            (string) $row['aggregate_id'],
            (string) $row['event_type'],
            $payload,
            (string) $row['idempotency_key'],
            (string) $row['correlation_id'],
            (int) $row['schema_version'],
            (int) $row['attempts'],
            (int) $row['max_attempts'],
            (string) $row['locked_by'],
            (string) $row['lock_token'],
            new DateTimeImmutable((string) $row['available_at']),
            new DateTimeImmutable((string) $row['created_at']),
            (string) $row['exchange_name'],
            (string) $row['routing_key'],
        );
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
