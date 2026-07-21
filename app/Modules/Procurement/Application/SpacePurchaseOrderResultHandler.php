<?php

declare(strict_types=1);

namespace Hapa\Modules\Procurement\Application;

use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;
use PDO;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class SpacePurchaseOrderResultHandler implements InboundMessageHandler
{
    public function __construct(private PDO $pdo)
    {
    }

    public function eventTypes(): array
    {
        return [
            'space.purchase_order.accepted',
            'space.purchase_order.rejected',
            'space.purchase_order.status_changed',
        ];
    }

    public function handle(MessageEnvelope $message): void
    {
        if ($message->schemaVersion !== 1 || !in_array($message->eventType, $this->eventTypes(), true)) {
            throw new InvalidArgumentException('Contratto esito acquisto Space non supportato.');
        }
        $purchaseId = $this->positiveId($message->payload, 'purchase_order_id');
        $version = $this->positiveInteger($message->payload, 'purchase_order_version');
        $status = match ($message->eventType) {
            'space.purchase_order.accepted' => 'accepted',
            'space.purchase_order.rejected' => 'rejected',
            default => $this->status($message->payload),
        };
        $externalId = $this->optionalString($message->payload, 'external_purchase_id', 200);
        if ($status === 'accepted' && $externalId === null) {
            throw new InvalidArgumentException('ID acquisto Space assente dall\'accettazione.');
        }
        $reason = $this->optionalString($message->payload, 'reason', 1000);
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE supplier_purchase_orders
SET external_purchase_id = COALESCE(:external_purchase_id, external_purchase_id),
    status = :status,
    accepted_at = CASE WHEN :accepted_status = 'accepted' THEN COALESCE(accepted_at, NOW()) ELSE accepted_at END,
    completed_at = CASE WHEN :completed_status = 'completed' THEN COALESCE(completed_at, NOW()) ELSE completed_at END,
    last_error = :last_error,
    version = GREATEST(version, :version),
    updated_at = NOW()
WHERE id = :id
  AND version <= :version
  AND status NOT IN ('completed', 'cancelled')
SQL);
        $statement->execute([
            'external_purchase_id' => $externalId,
            'status' => $status,
            'accepted_status' => $status,
            'completed_status' => $status,
            'last_error' => $status === 'rejected' ? ($reason ?? 'Acquisto rifiutato da Space.') : null,
            'version' => $version,
            'id' => $purchaseId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $this->pdo->prepare('SELECT 1 FROM supplier_purchase_orders WHERE id = :id');
            $exists->execute(['id' => $purchaseId]);
            if ($exists->fetchColumn() === false) {
                throw new HapaRuntimeException('Acquisto HAPA indicato dall\'esito Space non trovato.');
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function positiveId(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException('ID acquisto Space non valido.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $payload */
    private function positiveInteger(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function status(array $payload): string
    {
        $status = $this->optionalString($payload, 'status', 32);
        if ($status === null || !in_array($status, [
            'requested', 'accepted', 'partially_available', 'ready', 'completed',
            'rejected', 'cancelled', 'manual_review',
        ], true)) {
            throw new InvalidArgumentException('Stato acquisto Space non valido.');
        }

        return $status;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key, int $maximum): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || trim($value) !== $value || strlen($value) > $maximum) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
        }

        return $value;
    }
}
