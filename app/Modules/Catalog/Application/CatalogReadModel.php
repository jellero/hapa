<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use DateTimeImmutable;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Ui\CatalogOverview;
use PDO;

final class CatalogReadModel implements CatalogOverview
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /**
     * @return array{
     *   items: list<array<string, int|string|bool|null>>,
     *   metrics: array{total: int, pending_review: int, active: int, stale: int}
     * }
     */
    public function search(string $query, int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT item.id, item.sku, item.ean, item.name, item.onboarding_status, item.active, item.version,
       offer.purchase_cost_minor, offer.currency, offer.available_quantity,
       offer.source_version, offer.observed_at,
       COUNT(marketplace_offer.id) AS marketplace_offer_count
FROM catalog_items AS item
LEFT JOIN supplier_catalog_items AS offer
  ON offer.catalog_item_id = item.id
 AND offer.supplier_id = (SELECT id FROM suppliers WHERE code = 'space' LIMIT 1)
LEFT JOIN marketplace_offers AS marketplace_offer ON marketplace_offer.catalog_item_id = item.id
WHERE :query = ''
   OR item.sku ILIKE :pattern
   OR COALESCE(item.ean, '') ILIKE :pattern
   OR COALESCE(item.name, '') ILIKE :pattern
GROUP BY item.id, offer.id
ORDER BY offer.observed_at DESC NULLS LAST, item.sku ASC
LIMIT :limit
SQL);
        $statement->bindValue('query', trim($query));
        $statement->bindValue('pattern', '%' . trim($query) . '%');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $items = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $observedAt = is_string($row['observed_at']) ? new DateTimeImmutable($row['observed_at']) : null;
            $items[] = [
                'id' => (int) $row['id'],
                'sku' => (string) $row['sku'],
                'ean' => is_string($row['ean']) ? $row['ean'] : null,
                'name' => is_string($row['name']) ? $row['name'] : null,
                'onboarding_status' => (string) $row['onboarding_status'],
                'active' => (bool) $row['active'],
                'version' => (int) $row['version'],
                'purchase_cost_minor' => is_int($row['purchase_cost_minor'])
                    ? $row['purchase_cost_minor']
                    : (is_string($row['purchase_cost_minor']) ? (int) $row['purchase_cost_minor'] : null),
                'currency' => is_string($row['currency']) ? $row['currency'] : null,
                'available_quantity' => is_int($row['available_quantity'])
                    ? $row['available_quantity']
                    : (is_string($row['available_quantity']) ? (int) $row['available_quantity'] : null),
                'source_version' => is_string($row['source_version']) ? $row['source_version'] : null,
                'observed_at' => $observedAt?->format(DATE_ATOM),
                'age_seconds' => $observedAt === null ? null : max(0, time() - $observedAt->getTimestamp()),
                'marketplace_offer_count' => (int) $row['marketplace_offer_count'],
            ];
        }

        $metricsStatement = $this->connection()->query(<<<'SQL'
SELECT COUNT(*) AS total,
       COUNT(*) FILTER (WHERE onboarding_status = 'pending_review') AS pending_review,
       COUNT(*) FILTER (WHERE active) AS active,
       COUNT(*) FILTER (
           WHERE last_space_sync_at IS NULL OR last_space_sync_at < CURRENT_TIMESTAMP - INTERVAL '24 hours'
       ) AS stale
FROM catalog_items
SQL);
        if ($metricsStatement === false) {
            throw new \RuntimeException('Impossibile leggere le metriche catalogo.');
        }
        $metrics = $metricsStatement->fetch(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'metrics' => [
                'total' => (int) ($metrics['total'] ?? 0),
                'pending_review' => (int) ($metrics['pending_review'] ?? 0),
                'active' => (int) ($metrics['active'] ?? 0),
                'stale' => (int) ($metrics['stale'] ?? 0),
            ],
        ];
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
