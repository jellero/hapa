<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use DateTimeImmutable;
use Hapa\Core\Exception\HapaRuntimeException;
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
     * @param array<string, string> $filters
     * @return array{
     *   items: list<array<string, int|string|bool|null>>,
     *   metrics: array<string, int>,
     *   filter_options: array{feeds: list<string>, formats: list<string>, suppliers: list<array{id:string,name:?string}>}
     * }
     */
    public function search(string $query, int $limit = 100, array $filters = []): array
    {
        $limit = max(1, min($limit, 200));
        $conditions = [<<<'SQL'
(
    :query = ''
    OR item.sku ILIKE :pattern
    OR COALESCE(item.ean, '') ILIKE :pattern
    OR COALESCE(item.name, '') ILIKE :pattern
    OR COALESCE(offer.external_item_id, '') ILIKE :pattern
    OR COALESCE(offer.supplier_sku, '') ILIKE :pattern
    OR COALESCE(offer.artist, '') ILIKE :pattern
    OR COALESCE(offer.title, '') ILIKE :pattern
    OR COALESCE(offer.label, '') ILIKE :pattern
)
SQL];
        $parameters = [];
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['pending_review', 'approved', 'rejected'], true)) {
            $conditions[] = 'item.onboarding_status = :status';
            $parameters['status'] = $status;
        }
        $availability = (string) ($filters['availability'] ?? '');
        if ($availability === 'in_stock') {
            $conditions[] = 'COALESCE(offer.available_quantity, 0) > 0';
        } elseif ($availability === 'backorder') {
            $conditions[] = 'COALESCE(offer.available_quantity, 0) = 0 AND COALESCE(offer.backorder_quantity, 0) > 0 AND COALESCE(offer.active, FALSE)';
        } elseif ($availability === 'unavailable') {
            $conditions[] = 'COALESCE(offer.available_quantity, 0) = 0 AND COALESCE(offer.backorder_quantity, 0) = 0';
        }
        foreach (['feed_name' => 'offer.feed_name', 'format' => 'offer.format', 'supplier_id' => 'offer.space_supplier_id'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $conditions[] = $column . ' = :' . $key;
            $parameters[$key] = $value;
        }
        $sql = <<<'SQL'
SELECT item.id, item.sku, item.ean, item.name, item.onboarding_status, item.active, item.version,
       item.safety_stock, item.sellable_quantity, item.offers_calculated_at,
       offer.external_item_id, offer.supplier_sku, offer.purchase_cost_minor, offer.currency,
       offer.available_quantity, offer.backorder_quantity, offer.active AS offer_active,
       offer.source_version, offer.observed_at,
       offer.feed_name, offer.artist, offer.title, offer.format, offer.label,
       offer.category, offer.family, offer.group_name, offer.branch_suffix,
       offer.space_supplier_id, space_supplier.legal_name AS space_supplier_name,
       offer.delivery_time_days, offer.source_status,
       COALESCE(
           offer.product_url,
           NULLIF(offer.source_attributes->>'url_pagina', ''),
           NULLIF(offer.source_attributes->>'url', ''),
           NULLIF(offer.source_attributes->>'product_url', '')
       ) AS product_url,
       COALESCE(
           offer.image_url,
           NULLIF(offer.source_attributes->>'url_immagine', ''),
           NULLIF(offer.source_attributes->>'url_img', ''),
           NULLIF(offer.source_attributes->>'image_url', '')
       ) AS image_url,
       offer.precision_score, offer.release_date, offer.weight, offer.weight_unit,
       offer.missing_from_source, offer.temu_sync_enabled, offer.source_attributes::text AS source_attributes_json,
       COUNT(marketplace_offer.id) AS marketplace_offer_count
FROM catalog_items AS item
LEFT JOIN supplier_catalog_items AS offer
  ON offer.catalog_item_id = item.id
 AND offer.supplier_id = (SELECT id FROM suppliers WHERE code = 'space' LIMIT 1)
LEFT JOIN marketplace_offers AS marketplace_offer ON marketplace_offer.catalog_item_id = item.id
LEFT JOIN space_suppliers AS space_supplier ON space_supplier.space_supplier_id = offer.space_supplier_id
WHERE
SQL;
        $sql .= implode("\n  AND ", $conditions);
        $sql .= <<<'SQL'

GROUP BY item.id, offer.id, space_supplier.legal_name
ORDER BY offer.observed_at DESC NULLS LAST, item.sku ASC
LIMIT :limit
SQL;
        $statement = $this->connection()->prepare($sql);
        $statement->bindValue('query', trim($query));
        $statement->bindValue('pattern', '%' . trim($query) . '%');
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $items = array_values(array_map(
            static fn (array $row): array => self::hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        ));
        $metrics = $this->metrics();

        return ['items' => $items, 'metrics' => $metrics, 'filter_options' => $this->filterOptions()];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,int|string|bool|null>
     */
    private static function hydrate(array $row): array
    {
        $observedAt = is_string($row['observed_at']) ? new DateTimeImmutable($row['observed_at']) : null;

        return [
                'id' => (int) $row['id'],
                'sku' => (string) $row['sku'],
                'ean' => is_string($row['ean']) ? $row['ean'] : null,
                'name' => is_string($row['name']) ? $row['name'] : null,
                'onboarding_status' => (string) $row['onboarding_status'],
                'active' => (bool) $row['active'],
                'version' => (int) $row['version'],
                'safety_stock' => (int) $row['safety_stock'],
                'sellable_quantity' => (int) $row['sellable_quantity'],
                'offers_calculated_at' => is_string($row['offers_calculated_at'])
                    ? (new DateTimeImmutable($row['offers_calculated_at']))->format(DATE_ATOM)
                    : null,
                'purchase_cost_minor' => self::nullableInt($row['purchase_cost_minor']),
                'currency' => is_string($row['currency']) ? $row['currency'] : null,
                'available_quantity' => self::nullableInt($row['available_quantity']),
                'backorder_quantity' => self::nullableInt($row['backorder_quantity']) ?? 0,
                'external_item_id' => is_string($row['external_item_id']) ? $row['external_item_id'] : null,
                'supplier_sku' => is_string($row['supplier_sku']) ? $row['supplier_sku'] : null,
                'offer_active' => filter_var($row['offer_active'], FILTER_VALIDATE_BOOL),
                'source_version' => is_string($row['source_version']) ? $row['source_version'] : null,
                'observed_at' => $observedAt?->format(DATE_ATOM),
                'age_seconds' => $observedAt === null ? null : max(0, time() - $observedAt->getTimestamp()),
                'marketplace_offer_count' => (int) $row['marketplace_offer_count'],
                'feed_name' => is_string($row['feed_name']) ? $row['feed_name'] : null,
                'artist' => is_string($row['artist']) ? $row['artist'] : null,
                'title' => is_string($row['title']) ? $row['title'] : null,
                'format' => is_string($row['format']) ? $row['format'] : null,
                'label' => is_string($row['label']) ? $row['label'] : null,
                'category' => is_string($row['category']) ? $row['category'] : null,
                'family' => is_string($row['family']) ? $row['family'] : null,
                'group' => is_string($row['group_name']) ? $row['group_name'] : null,
                'branch_suffix' => is_string($row['branch_suffix']) ? $row['branch_suffix'] : null,
                'space_supplier_id' => is_string($row['space_supplier_id']) ? $row['space_supplier_id'] : null,
                'space_supplier_name' => is_string($row['space_supplier_name']) ? $row['space_supplier_name'] : null,
                'delivery_time_days' => self::nullableInt($row['delivery_time_days']),
                'source_status' => self::nullableInt($row['source_status']),
                'precision_score' => self::nullableInt($row['precision_score']),
                'product_url' => is_string($row['product_url']) ? $row['product_url'] : null,
                'image_url' => is_string($row['image_url']) ? $row['image_url'] : null,
                'release_date' => is_string($row['release_date']) ? $row['release_date'] : null,
                'weight' => is_string($row['weight']) ? $row['weight'] : null,
                'weight_unit' => is_string($row['weight_unit']) ? $row['weight_unit'] : null,
                'missing_from_source' => filter_var($row['missing_from_source'], FILTER_VALIDATE_BOOL),
                'temu_sync_enabled' => filter_var($row['temu_sync_enabled'], FILTER_VALIDATE_BOOL),
                'source_attributes_json' => is_string($row['source_attributes_json'])
                    ? self::prettyJson($row['source_attributes_json'])
                    : '{}',
            ];
    }

    /** @return array<string, int> */
    private function metrics(): array
    {
        $metricsStatement = $this->connection()->query(<<<'SQL'
SELECT COUNT(*) AS total,
       COUNT(*) FILTER (WHERE item.onboarding_status = 'pending_review') AS pending_review,
       COUNT(*) FILTER (WHERE item.active) AS active,
       COUNT(*) FILTER (
           WHERE item.last_space_sync_at IS NULL OR item.last_space_sync_at < CURRENT_TIMESTAMP - INTERVAL '24 hours'
       ) AS stale,
       COUNT(*) FILTER (WHERE COALESCE(source.available_quantity, 0) > 0) AS in_stock,
       COUNT(*) FILTER (
           WHERE COALESCE(source.available_quantity, 0) = 0
             AND COALESCE(source.backorder_quantity, 0) > 0
             AND COALESCE(source.active, FALSE)
       ) AS backorder,
       COUNT(*) FILTER (
           WHERE COALESCE(source.available_quantity, 0) = 0
             AND COALESCE(source.backorder_quantity, 0) = 0
       ) AS unavailable
FROM catalog_items item
LEFT JOIN supplier_catalog_items source
  ON source.catalog_item_id = item.id
 AND source.supplier_id = (SELECT id FROM suppliers WHERE code = 'space' LIMIT 1)
SQL);
        if ($metricsStatement === false) {
            throw new HapaRuntimeException('Impossibile leggere le metriche catalogo.');
        }
        $metrics = $metricsStatement->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($metrics['total'] ?? 0),
            'pending_review' => (int) ($metrics['pending_review'] ?? 0),
            'active' => (int) ($metrics['active'] ?? 0),
            'stale' => (int) ($metrics['stale'] ?? 0),
            'in_stock' => (int) ($metrics['in_stock'] ?? 0),
            'backorder' => (int) ($metrics['backorder'] ?? 0),
            'unavailable' => (int) ($metrics['unavailable'] ?? 0),
        ];
    }

    /** @return array{feeds: list<string>, formats: list<string>, suppliers: list<array{id:string,name:?string}>} */
    private function filterOptions(): array
    {
        $values = [];
        foreach ([
            'feeds' => 'feed_name',
            'formats' => 'format',
        ] as $key => $column) {
            $statement = $this->connection()->query(sprintf(
                "SELECT DISTINCT %s
                 FROM supplier_catalog_items
                 WHERE supplier_id = (SELECT id FROM suppliers WHERE code = 'space' LIMIT 1)
                   AND %s IS NOT NULL
                   AND BTRIM(%s) <> ''
                 ORDER BY %s",
                $column,
                $column,
                $column,
                $column,
            ));
            $values[$key] = $statement === false
                ? []
                : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
        }
        $supplierStatement = $this->connection()->query(<<<'SQL'
SELECT DISTINCT offer.space_supplier_id, supplier.legal_name
FROM supplier_catalog_items offer
LEFT JOIN space_suppliers supplier ON supplier.space_supplier_id = offer.space_supplier_id
WHERE offer.supplier_id = (SELECT id FROM suppliers WHERE code = 'space' LIMIT 1)
  AND offer.space_supplier_id IS NOT NULL
ORDER BY supplier.legal_name NULLS LAST, offer.space_supplier_id
SQL);
        $values['suppliers'] = $supplierStatement === false ? [] : array_values(array_map(
            static fn (array $row): array => [
                'id' => (string) $row['space_supplier_id'],
                'name' => is_string($row['legal_name']) ? $row['legal_name'] : null,
            ],
            $supplierStatement->fetchAll(PDO::FETCH_ASSOC),
        ));

        /** @var array{feeds: list<string>, formats: list<string>, suppliers: list<array{id:string,name:?string}>} $values */
        return $values;
    }

    private static function prettyJson(string $json): string
    {
        try {
            return json_encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $json;
        }
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_int($value) || is_string($value) ? (int) $value : null;
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
