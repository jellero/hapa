<?php

declare(strict_types=1);

namespace Hapa\Core\Observability;

use Hapa\Core\Database\ConnectionFactory;
use PDO;
use Hapa\Core\Exception\HapaRuntimeException;

final class RuntimeOverview
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /**
     * @return array{
     *   business: array{open_orders: int, customers: int, catalog_items: int, shipments_today: int},
     *   inbox: array<string, int>, outbox: array<string, int>,
     *   lag_seconds: array{inbox_failed_oldest: int, outbox_due_oldest: int},
     *   audit_last_24h: int
     * }
     */
    public function snapshot(): array
    {
        return [
            'business' => [
                'open_orders' => $this->count('orders', "status NOT IN ('fulfilment_completed', 'completed_partial', 'cancelled')"),
                'customers' => $this->count('customers'),
                'catalog_items' => $this->count('catalog_items'),
                'shipments_today' => $this->count('shipments', 'created_at >= CURRENT_DATE'),
            ],
            'inbox' => $this->counts('inbox_messages'),
            'outbox' => $this->counts('outbox_messages'),
            'lag_seconds' => [
                'inbox_failed_oldest' => $this->age('inbox_messages', "status = 'failed'", 'received_at'),
                'outbox_due_oldest' => $this->age('outbox_messages', "status IN ('pending', 'retry')", 'available_at'),
            ],
            'audit_last_24h' => $this->count('audit_logs', "created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'"),
        ];
    }

    /** @return array<string, int> */
    private function counts(string $table): array
    {
        $this->assertTable($table);
        $statement = $this->connection()->query(sprintf(
            'SELECT status, COUNT(*) AS total FROM %s GROUP BY status ORDER BY status',
            $table,
        ));
        if ($statement === false) {
            throw new HapaRuntimeException('Impossibile calcolare le metriche runtime HAPA.');
        }

        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    private function count(string $table, ?string $condition = null): int
    {
        $this->assertTable($table);
        $this->assertCondition($condition);
        $statement = $this->connection()->query(sprintf(
            'SELECT COUNT(*) FROM %s%s',
            $table,
            $condition === null ? '' : ' WHERE ' . $condition,
        ));
        if ($statement === false) {
            throw new HapaRuntimeException('Impossibile calcolare una metrica HAPA.');
        }

        return (int) $statement->fetchColumn();
    }

    private function age(string $table, string $condition, string $column): int
    {
        $this->assertTable($table);
        $this->assertCondition($condition);
        if (!in_array($column, ['received_at', 'available_at'], true)) {
            throw new HapaRuntimeException('Colonna metrica HAPA non valida.');
        }
        $statement = $this->connection()->query(sprintf(
            "SELECT COALESCE(EXTRACT(EPOCH FROM CURRENT_TIMESTAMP - MIN(%s)), 0)::BIGINT FROM %s WHERE %s",
            $column,
            $table,
            $condition,
        ));
        if ($statement === false) {
            throw new HapaRuntimeException('Impossibile calcolare il lag HAPA.');
        }

        return max(0, (int) $statement->fetchColumn());
    }

    private function assertTable(string $table): void
    {
        if (!in_array($table, [
            'orders', 'customers', 'catalog_items', 'shipments',
            'inbox_messages', 'outbox_messages', 'audit_logs',
        ], true)) {
            throw new HapaRuntimeException('Tabella metrica HAPA non valida.');
        }
    }

    private function assertCondition(?string $condition): void
    {
        if ($condition !== null && !in_array($condition, [
            "status NOT IN ('fulfilment_completed', 'completed_partial', 'cancelled')",
            'created_at >= CURRENT_DATE',
            "created_at >= CURRENT_TIMESTAMP - INTERVAL '24 hours'",
            "status = 'failed'",
            "status IN ('pending', 'retry')",
        ], true)) {
            throw new HapaRuntimeException('Condizione metrica HAPA non valida.');
        }
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
