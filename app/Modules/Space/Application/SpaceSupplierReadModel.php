<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Application;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Ui\SpaceSupplierOverview;
use PDO;

final class SpaceSupplierReadModel implements SpaceSupplierOverview
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    public function search(string $query, string $status = '', int $limit = 200): array
    {
        $conditions = ['source_observed_at IS NOT NULL', <<<'SQL'
(:query = '' OR space_supplier_id ILIKE :pattern OR COALESCE(legal_name, '') ILIKE :pattern
 OR COALESCE(code, '') ILIKE :pattern OR COALESCE(city, '') ILIKE :pattern
 OR COALESCE(country, '') ILIKE :pattern)
SQL];
        $parameters = ['query' => trim($query), 'pattern' => '%' . trim($query) . '%'];
        if ($status === 'active' || $status === 'inactive') {
            $conditions[] = 'active = :active';
            $parameters['active'] = $status === 'active';
        }
        $statement = $this->connection()->prepare(
            'SELECT * FROM space_suppliers WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY legal_name NULLS LAST, space_supplier_id LIMIT :limit',
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value, is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
        }
        $statement->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(static fn (array $row): array => [
            'space_supplier_id' => (string) $row['space_supplier_id'],
            'legal_name' => is_string($row['legal_name']) ? $row['legal_name'] : null,
            'code' => is_string($row['code']) ? $row['code'] : null,
            'currency' => is_string($row['currency']) ? $row['currency'] : null,
            'delivery_days' => is_numeric($row['delivery_days']) ? (int) $row['delivery_days'] : null,
            'precision_score' => is_numeric($row['precision_score']) ? (int) $row['precision_score'] : null,
            'closing_order' => is_string($row['closing_order']) ? $row['closing_order'] : null,
            'city' => is_string($row['city']) ? $row['city'] : null,
            'province' => is_string($row['province']) ? $row['province'] : null,
            'postal_code' => is_string($row['postal_code']) ? $row['postal_code'] : null,
            'address' => is_string($row['address']) ? $row['address'] : null,
            'country' => is_string($row['country']) ? $row['country'] : null,
            'country_code' => is_string($row['country_code']) ? $row['country_code'] : null,
            'active' => (bool) $row['active'],
            'source_observed_at' => is_string($row['source_observed_at']) ? $row['source_observed_at'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
        $metricsStatement = $this->connection()->query(
            'SELECT COUNT(*) total, COUNT(*) FILTER (WHERE active) active, '
            . 'COUNT(*) FILTER (WHERE NOT active) inactive, COUNT(DISTINCT country_code) FILTER (WHERE country_code IS NOT NULL) countries '
            . 'FROM space_suppliers WHERE source_observed_at IS NOT NULL',
        );
        $metrics = $metricsStatement === false ? [] : $metricsStatement->fetch(PDO::FETCH_ASSOC);
        $metrics = is_array($metrics) ? $metrics : [];

        return ['items' => array_values($items), 'metrics' => [
            'total' => (int) ($metrics['total'] ?? 0),
            'active' => (int) ($metrics['active'] ?? 0),
            'inactive' => (int) ($metrics['inactive'] ?? 0),
            'countries' => (int) ($metrics['countries'] ?? 0),
        ]];
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
