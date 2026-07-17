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

        $entries = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $before = $row['before_data'] === null
                ? null
                : json_decode((string) $row['before_data'], true, 512, JSON_THROW_ON_ERROR);
            $after = $row['after_data'] === null
                ? null
                : json_decode((string) $row['after_data'], true, 512, JSON_THROW_ON_ERROR);
            $entries[] = [
                'id' => (int) $row['id'],
                'created_at' => (string) $row['created_at'],
                'actor_id' => $row['actor_id'] === null ? null : (string) $row['actor_id'],
                'actor_name' => $row['actor_name'] === null ? null : (string) $row['actor_name'],
                'actor_email' => $row['actor_email'] === null ? null : (string) $row['actor_email'],
                'action' => (string) $row['action'],
                'entity_type' => (string) $row['entity_type'],
                'entity_id' => (string) $row['entity_id'],
                'correlation_id' => $row['correlation_id'] === null ? null : (string) $row['correlation_id'],
                'before' => is_array($before) ? $before : null,
                'after' => is_array($after) ? $after : null,
            ];
        }

        return $entries;
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
}
