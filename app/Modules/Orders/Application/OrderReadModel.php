<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Application;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Ui\OrderOverview;
use JsonException;
use PDO;

final class OrderReadModel implements OrderOverview
{
    /** @var array<string, non-empty-list<string>> */
    private const STATUS_FILTERS = [
        'to_process' => ['new', 'imported', 'accepted', 'waiting_address'],
        'waiting_goods' => ['sent_to_space', 'waiting_goods', 'partial_available'],
        'picking' => ['goods_available', 'picking', 'partial_confirmed', 'ready_for_carrier', 'label_available', 'tracking_sent'],
        'manual_review' => ['manual_review'],
        'completed' => ['fulfilment_completed', 'completed_partial'],
        'cancelled' => ['cancelled'],
    ];

    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, string $status, int $limit = 100): array
    {
        $query = trim($query);
        $statuses = self::STATUS_FILTERS[$status] ?? [];
        $limit = max(1, min(200, $limit));
        $statusSql = '';
        $parameters = [
            'query' => $query,
            'pattern' => '%' . $query . '%',
        ];
        if ($statuses !== []) {
            $placeholders = [];
            foreach ($statuses as $index => $value) {
                $key = 'status_' . $index;
                $placeholders[] = ':' . $key;
                $parameters[$key] = $value;
            }
            $statusSql = 'AND customer_order.status IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = $this->connection()->prepare(sprintf(<<<'SQL'
SELECT customer_order.id, customer_order.order_number, customer_order.external_order_id,
       customer_order.status, customer_order.origin, customer_order.origin_reference,
       customer_order.currency, customer_order.grand_total_minor, customer_order.version,
       COALESCE(customer_order.placed_at, customer_order.created_at) AS ordered_at,
       customer_order.updated_at,
       customer.customer_code, customer.display_name AS customer_name,
       marketplace.code AS marketplace_code, marketplace.name AS marketplace_name,
       marketplace_account.code AS marketplace_account_code,
       marketplace_account.display_name AS marketplace_account_name,
       (SELECT COUNT(*) FROM order_lines line WHERE line.order_id = customer_order.id) AS line_count,
       (SELECT COUNT(*) FROM shipments shipment WHERE shipment.order_id = customer_order.id) AS shipment_count,
       (SELECT string_agg(DISTINCT shipment.tracking_number, ', ' ORDER BY shipment.tracking_number)
          FROM shipments shipment
         WHERE shipment.order_id = customer_order.id AND shipment.tracking_number IS NOT NULL) AS tracking_numbers
FROM orders customer_order
LEFT JOIN customers customer ON customer.id = customer_order.customer_id
LEFT JOIN marketplaces marketplace ON marketplace.id = customer_order.marketplace_id
LEFT JOIN marketplace_accounts marketplace_account ON marketplace_account.id = customer_order.marketplace_account_id
WHERE (
    :query = ''
    OR customer_order.order_number ILIKE :pattern
    OR customer_order.external_order_id ILIKE :pattern
    OR COALESCE(customer_order.origin_reference, '') ILIKE :pattern
    OR COALESCE(customer.customer_code, '') ILIKE :pattern
    OR COALESCE(customer.display_name, '') ILIKE :pattern
    OR COALESCE(customer.email, '') ILIKE :pattern
    OR COALESCE(marketplace.code, '') ILIKE :pattern
    OR EXISTS (
        SELECT 1 FROM order_lines line
        WHERE line.order_id = customer_order.id
          AND (line.sku ILIKE :pattern OR COALESCE(line.ean, '') ILIKE :pattern)
    )
    OR EXISTS (
        SELECT 1 FROM shipments shipment
        WHERE shipment.order_id = customer_order.id
          AND COALESCE(shipment.tracking_number, '') ILIKE :pattern
    )
)
%s
ORDER BY COALESCE(customer_order.placed_at, customer_order.created_at) DESC, customer_order.id DESC
LIMIT :limit
SQL, $statusSql));
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $orders = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $orders[] = $this->normalizeOrder($row);
        }

        return $orders;
    }

    /** @return array<string, mixed>|null */
    public function detail(string $orderNumber): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT customer_order.*,
       customer.customer_code, customer.display_name AS customer_name,
       customer.email AS customer_email, customer.phone AS customer_phone,
       marketplace.code AS marketplace_code, marketplace.name AS marketplace_name,
       marketplace_account.code AS marketplace_account_code,
       marketplace_account.display_name AS marketplace_account_name,
       marketplace_account.connector_code AS connector_code
FROM orders customer_order
LEFT JOIN customers customer ON customer.id = customer_order.customer_id
LEFT JOIN marketplaces marketplace ON marketplace.id = customer_order.marketplace_id
LEFT JOIN marketplace_accounts marketplace_account ON marketplace_account.id = customer_order.marketplace_account_id
WHERE customer_order.order_number = :order_number
SQL);
        $statement->execute(['order_number' => strtoupper(trim($orderNumber))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $order = $this->normalizeOrder($row);
        $order['customer_email'] = self::nullable($row['customer_email']);
        $order['customer_phone'] = self::nullable($row['customer_phone']);
        $order['marketplace_name'] = self::nullable($row['marketplace_name']);
        $order['marketplace_account_name'] = self::nullable($row['marketplace_account_name']);
        $order['connector_code'] = self::nullable($row['connector_code']);
        $order['subtotal_minor'] = self::nullableInt($row['subtotal_minor']);
        $order['shipping_total_minor'] = self::nullableInt($row['shipping_total_minor']);
        $order['discount_total_minor'] = self::nullableInt($row['discount_total_minor']);
        $order['tax_total_minor'] = self::nullableInt($row['tax_total_minor']);
        $order['accepted_at'] = self::nullable($row['accepted_at']);
        $order['completed_at'] = self::nullable($row['completed_at']);
        $order['created_at'] = (string) $row['created_at'];
        $order['shipping_address'] = self::decodeObject($row['shipping_address']);
        $order['billing_address'] = self::decodeObject($row['billing_address']);
        $orderId = (int) $row['id'];
        $order['lines'] = $this->lines($orderId);
        $order['transitions'] = $this->transitions($orderId);
        $order['purchases'] = $this->purchases($orderId);
        $order['shipments'] = $this->shipments($orderId);
        $order['legacy_deliveries'] = $this->legacyDeliveries($orderId);

        return $order;
    }

    /** @return list<array<string, mixed>> */
    private function lines(int $orderId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT line.id, line.line_number, line.sku, line.external_line_id, line.ean,
       line.description_snapshot, line.quantity_ordered, line.quantity_available,
       line.quantity_to_ship, line.quantity_to_cancel, line.partial_reason,
       line.unit_price_minor, line.tax_rate_basis_points, line.discount_total_minor,
       line.line_total_minor, item.name AS catalog_item_name
FROM order_lines line
LEFT JOIN catalog_items item ON item.id = line.catalog_item_id
WHERE line.order_id = :order_id
ORDER BY line.line_number, line.id
SQL);
        $statement->execute(['order_id' => $orderId]);
        $lines = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach (['id', 'line_number', 'quantity_ordered', 'quantity_available', 'quantity_to_ship', 'quantity_to_cancel', 'discount_total_minor'] as $field) {
                $row[$field] = (int) $row[$field];
            }
            foreach (['unit_price_minor', 'tax_rate_basis_points', 'line_total_minor'] as $field) {
                $row[$field] = self::nullableInt($row[$field]);
            }
            $lines[] = $row;
        }

        return $lines;
    }

    /** @return list<array<string, mixed>> */
    private function transitions(int $orderId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT from_status, to_status, reason, version, occurred_at
FROM order_transitions
WHERE order_id = :order_id
ORDER BY occurred_at DESC, id DESC
SQL);
        $statement->execute(['order_id' => $orderId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['version'] = (int) $row['version'];
        }
        unset($row);

        return array_values($rows);
    }

    /** @return list<array<string, mixed>> */
    private function purchases(int $orderId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT purchase.id, purchase.purchase_number, purchase.external_purchase_id,
       purchase.status, purchase.currency, purchase.subtotal_minor,
       purchase.tax_total_minor, purchase.grand_total_minor, purchase.version,
       purchase.submitted_at, purchase.accepted_at, purchase.completed_at,
       purchase.created_at, purchase.updated_at,
       supplier.code AS supplier_code, supplier.name AS supplier_name,
       (SELECT COUNT(*) FROM supplier_purchase_order_lines line
         WHERE line.purchase_order_id = purchase.id) AS line_count
FROM supplier_purchase_orders purchase
JOIN suppliers supplier ON supplier.id = purchase.supplier_id
WHERE purchase.order_id = :order_id
ORDER BY purchase.created_at DESC, purchase.id DESC
SQL);
        $statement->execute(['order_id' => $orderId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['version'] = (int) $row['version'];
            $row['line_count'] = (int) $row['line_count'];
            foreach (['subtotal_minor', 'tax_total_minor', 'grand_total_minor'] as $field) {
                $row[$field] = self::nullableInt($row[$field]);
            }
        }
        unset($row);

        return array_values($rows);
    }

    /** @return list<array<string, mixed>> */
    private function shipments(int $orderId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT id, provider, external_shipment_id, tracking_number, label_reference,
       status, packages, weight_kg, created_at, updated_at
FROM shipments
WHERE order_id = :order_id
ORDER BY created_at DESC, id DESC
SQL);
        $statement->execute(['order_id' => $orderId]);
        $shipments = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $shipmentId = (int) $row['id'];
            $row['id'] = $shipmentId;
            $row['packages'] = (int) $row['packages'];
            $row['packages_detail'] = $this->packages($shipmentId);
            $row['labels'] = $this->labels($shipmentId);
            $shipments[] = $row;
        }

        return $shipments;
    }

    /** @return list<array<string, mixed>> */
    private function packages(int $shipmentId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT package_number, weight_grams, length_mm, width_mm, height_mm
FROM shipment_packages
WHERE shipment_id = :shipment_id
ORDER BY package_number
SQL);
        $statement->execute(['shipment_id' => $shipmentId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            foreach (['package_number', 'weight_grams'] as $field) {
                $row[$field] = (int) $row[$field];
            }
            foreach (['length_mm', 'width_mm', 'height_mm'] as $field) {
                $row[$field] = self::nullableInt($row[$field]);
            }
        }
        unset($row);

        return array_values($rows);
    }

    /** @return list<array<string, mixed>> */
    private function labels(int $shipmentId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT format, storage_reference, checksum_sha256, generated_at, expires_at
FROM shipment_labels
WHERE shipment_id = :shipment_id
ORDER BY generated_at DESC, id DESC
SQL);
        $statement->execute(['shipment_id' => $shipmentId]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function legacyDeliveries(int $orderId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT provider, operation, http_status, status, attempt, correlation_id,
       error_code, created_at, updated_at
FROM legacy_external_deliveries
WHERE order_id = :order_id
ORDER BY created_at DESC, id DESC
LIMIT 100
SQL);
        $statement->execute(['order_id' => $orderId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['http_status'] = self::nullableInt($row['http_status']);
            $row['attempt'] = (int) $row['attempt'];
        }
        unset($row);

        return array_values($rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeOrder(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'order_number' => (string) $row['order_number'],
            'external_order_id' => (string) $row['external_order_id'],
            'status' => (string) $row['status'],
            'origin' => (string) $row['origin'],
            'origin_reference' => self::nullable($row['origin_reference']),
            'currency' => (string) $row['currency'],
            'grand_total_minor' => self::nullableInt($row['grand_total_minor']),
            'version' => (int) $row['version'],
            'ordered_at' => (string) ($row['ordered_at'] ?? $row['placed_at'] ?? $row['created_at']),
            'updated_at' => (string) $row['updated_at'],
            'customer_code' => self::nullable($row['customer_code'] ?? null),
            'customer_name' => self::nullable($row['customer_name'] ?? null),
            'marketplace_code' => self::nullable($row['marketplace_code'] ?? null),
            'marketplace_account_code' => self::nullable($row['marketplace_account_code'] ?? null),
            'line_count' => (int) ($row['line_count'] ?? 0),
            'shipment_count' => (int) ($row['shipment_count'] ?? 0),
            'tracking_numbers' => self::nullable($row['tracking_numbers'] ?? null),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function decodeObject(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
