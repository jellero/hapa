<?php

declare(strict_types=1);

namespace Hapa\Core\Audit;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Logging\SensitiveDataRedactor;
use Hapa\Core\Security\UserIdentity;
use JsonException;
use PDO;

final class AuditLogger
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly Clock $clock,
        private readonly SensitiveDataRedactor $redactor,
    ) {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @throws JsonException
     */
    public function record(
        ?UserIdentity $actor,
        string $action,
        string $entityType,
        string $entityId,
        ?array $before,
        ?array $after,
        ?string $correlationId,
    ): void {
        $statement = $this->connection()->prepare(<<<'SQL'
INSERT INTO audit_logs (
    actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at
) VALUES (
    :actor_id, :action, :entity_type, :entity_id, :before_data, :after_data, :correlation_id, :created_at
)
SQL);
        $statement->execute([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $before === null ? null : json_encode($this->redactor->redact($before), JSON_THROW_ON_ERROR),
            'after_data' => $after === null ? null : json_encode($this->redactor->redact($after), JSON_THROW_ON_ERROR),
            'correlation_id' => $correlationId,
            'created_at' => $this->clock->now()->format(DATE_ATOM),
        ]);
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
