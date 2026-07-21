<?php

declare(strict_types=1);

namespace Hapa\Core\Audit;

use Hapa\Core\Database\ConnectionFactory;
use JsonException;
use PDO;

final class AuditReadModel
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /** @return list<array<string, mixed>> @throws JsonException */
    public function search(string $query, string $entityType, int $limit = 200): array
    {
        $query = trim($query);
        $entityType = in_array($entityType, $this->entityTypes(), true) ? $entityType : '';
        $limit = max(1, min(500, $limit));
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT audit.id, audit.created_at, audit.actor_id, audit.action, audit.entity_type,
       audit.entity_id, audit.correlation_id, audit.before_data, audit.after_data,
       actor.display_name AS actor_name, actor.email AS actor_email
FROM audit_logs audit
LEFT JOIN app_users actor ON actor.id = audit.actor_id
WHERE (:entity_type = '' OR audit.entity_type = :entity_type)
  AND (
      :query = ''
      OR audit.action ILIKE :pattern
      OR audit.entity_type ILIKE :pattern
      OR audit.entity_id ILIKE :pattern
      OR COALESCE(audit.actor_id, '') ILIKE :pattern
      OR COALESCE(actor.display_name, '') ILIKE :pattern
      OR COALESCE(actor.email, '') ILIKE :pattern
      OR COALESCE(audit.correlation_id, '') ILIKE :pattern
  )
ORDER BY audit.created_at DESC, audit.id DESC
LIMIT :limit
SQL);
        $statement->bindValue('entity_type', $entityType);
        $statement->bindValue('query', $query);
        $statement->bindValue('pattern', '%' . $query . '%');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map(
            static fn (array $row): array => self::hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        ));
    }

    /** @return list<string> */
    public function entityTypes(): array
    {
        $statement = $this->connection()->query(<<<'SQL'
SELECT DISTINCT entity_type
FROM audit_logs
WHERE btrim(entity_type) <> ''
ORDER BY entity_type
SQL);
        if ($statement === false) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $entityType): string => (string) $entityType,
            $statement->fetchAll(PDO::FETCH_COLUMN),
        ));
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     *  @throws JsonException
     */
    private static function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'created_at' => (string) $row['created_at'],
            'actor_id' => self::nullableString($row['actor_id']),
            'actor_name' => self::nullableString($row['actor_name']),
            'actor_email' => self::nullableString($row['actor_email']),
            'action' => (string) $row['action'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => (string) $row['entity_id'],
            'correlation_id' => self::nullableString($row['correlation_id']),
            'before' => self::jsonObject($row['before_data']),
            'after' => self::jsonObject($row['after_data']),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** @return array<string, mixed>|null @throws JsonException */
    private static function jsonObject(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }
}
