<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Infrastructure\Automation;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Outbox\ClaimedOutboxMessage;
use Hapa\Core\Outbox\OutboxMessageHandler;
use Hapa\Core\Outbox\PermanentProcessingFailure;
use PDO;

final readonly class OrderAuditOutboxHandler implements OutboxMessageHandler
{
    public function __construct(private PDO $pdo, private Clock $clock)
    {
    }

    public function eventTypes(): array
    {
        return [
            'order.created',
            'order.status_changed',
            'order.address_changed',
            'order.availability_changed',
        ];
    }

    public function handle(ClaimedOutboxMessage $message): void
    {
        if ($message->schemaVersion !== 1) {
            throw new PermanentProcessingFailure(sprintf(
                'Versione schema %d non supportata per %s.',
                $message->schemaVersion,
                $message->eventType,
            ));
        }

        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO audit_logs (
    actor_id, action, entity_type, entity_id, before_data, after_data,
    correlation_id, source_outbox_id, created_at
) VALUES (
    NULL, :action, :entity_type, :entity_id, NULL, CAST(:after_data AS JSONB),
    :correlation_id, :source_outbox_id, :created_at
)
ON CONFLICT (source_outbox_id) DO NOTHING
SQL);
        $statement->execute([
            'action' => $message->eventType,
            'entity_type' => $message->aggregateType,
            'entity_id' => $message->aggregateId,
            'after_data' => json_encode($message->payload, JSON_THROW_ON_ERROR),
            'correlation_id' => $message->correlationId,
            'source_outbox_id' => $message->id,
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
