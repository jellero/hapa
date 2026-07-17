<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Application;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Ui\ShipmentOverview;
use PDO;

final class ShipmentReadModel implements ShipmentOverview
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, string $status, int $limit = 100): array
    {
        $query = trim($query);
        $status = in_array($status, ['pending', 'created', 'label_available', 'shipped', 'error', 'cancelled'], true)
            ? $status
            : '';
        $limit = max(1, min(200, $limit));
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT shipment.id, shipment.provider, shipment.external_shipment_id,
       shipment.tracking_number, shipment.status, shipment.packages,
       shipment.weight_kg, shipment.created_at, shipment.updated_at,
       customer_order.order_number, customer_order.status AS order_status,
       customer.customer_code, customer.display_name AS customer_name,
       (SELECT COUNT(*) FROM shipment_packages package WHERE package.shipment_id = shipment.id) AS package_detail_count,
       (SELECT COALESCE(SUM(package.weight_grams), 0) FROM shipment_packages package WHERE package.shipment_id = shipment.id) AS package_weight_grams,
       (SELECT COUNT(*) FROM shipment_labels label WHERE label.shipment_id = shipment.id) AS label_count,
       (SELECT label.format FROM shipment_labels label WHERE label.shipment_id = shipment.id ORDER BY label.generated_at DESC, label.id DESC LIMIT 1) AS latest_label_format,
       (SELECT label.generated_at FROM shipment_labels label WHERE label.shipment_id = shipment.id ORDER BY label.generated_at DESC, label.id DESC LIMIT 1) AS latest_label_generated_at,
       (SELECT label.expires_at FROM shipment_labels label WHERE label.shipment_id = shipment.id ORDER BY label.generated_at DESC, label.id DESC LIMIT 1) AS latest_label_expires_at
FROM shipments shipment
JOIN orders customer_order ON customer_order.id = shipment.order_id
LEFT JOIN customers customer ON customer.id = customer_order.customer_id
WHERE (:status = '' OR shipment.status = :status)
  AND (
      :query = ''
      OR customer_order.order_number ILIKE :pattern
      OR COALESCE(shipment.external_shipment_id, '') ILIKE :pattern
      OR COALESCE(shipment.tracking_number, '') ILIKE :pattern
      OR shipment.provider ILIKE :pattern
      OR COALESCE(customer.customer_code, '') ILIKE :pattern
      OR COALESCE(customer.display_name, '') ILIKE :pattern
  )
ORDER BY shipment.updated_at DESC, shipment.id DESC
LIMIT :limit
SQL);
        $statement->bindValue('status', $status);
        $statement->bindValue('query', $query);
        $statement->bindValue('pattern', '%' . $query . '%');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            foreach (['id', 'packages', 'package_detail_count', 'package_weight_grams', 'label_count'] as $field) {
                $row[$field] = (int) $row[$field];
            }
        }
        unset($row);

        return array_values($rows);
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
