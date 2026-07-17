<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Modules\Orders\Application\OrderReadModel;
use Hapa\Modules\Shipping\Application\ShipmentReadModel;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class OrderReadModelTest extends TestCase
{
    private PDO $pdo;
    private OrderReadModel $readModel;
    private ShipmentReadModel $shipmentReadModel;
    private string $orderNumber;
    private int $orderId;
    private int $customerId;
    private int $marketplaceId;
    private int $shipmentId;
    private int $purchaseId;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $this->readModel = new OrderReadModel($connections);
            $this->shipmentReadModel = new ShipmentReadModel($connections);
            $this->seed();
        } catch (Throwable $exception) {
            $this->cleanup();
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    public function testSearchFindsAnOrderByTrackingAndAppliesTheOperationalStatusFilter(): void
    {
        $orders = $this->readModel->search('track-' . strtolower(substr($this->orderNumber, 4)), 'waiting_goods');

        self::assertCount(1, $orders);
        self::assertSame($this->orderNumber, $orders[0]['order_number']);
        self::assertSame('waiting_goods', $orders[0]['status']);
        self::assertSame(1, $orders[0]['line_count']);
        self::assertSame(1, $orders[0]['shipment_count']);
        self::assertSame(1299, $orders[0]['grand_total_minor']);
    }

    public function testDetailComposesCommercialLogisticsAndRedactedLegacyData(): void
    {
        $order = $this->readModel->detail($this->orderNumber);

        self::assertNotNull($order);
        self::assertSame('Cliente read model', $order['customer_name']);
        self::assertSame('Roma', $order['shipping_address']['city']);
        self::assertSame('SKU-' . substr($this->orderNumber, 4), $order['lines'][0]['sku']);
        self::assertSame('Space', $order['purchases'][0]['supplier_name']);
        self::assertSame('GLS', $order['shipments'][0]['provider']);
        self::assertSame(1200, $order['shipments'][0]['packages_detail'][0]['weight_grams']);
        self::assertSame('PDF', $order['shipments'][0]['labels'][0]['format']);
        self::assertSame('sent_to_space', $order['transitions'][0]['to_status']);
        self::assertSame('space', $order['legacy_deliveries'][0]['provider']);
        self::assertArrayNotHasKey('request_payload', $order['legacy_deliveries'][0]);
        self::assertArrayNotHasKey('response_payload', $order['legacy_deliveries'][0]);
        self::assertArrayNotHasKey('error_message', $order['legacy_deliveries'][0]);
    }

    public function testShipmentReadModelFindsTrackingAndLabelMetadata(): void
    {
        $shipments = $this->shipmentReadModel->search(
            'track-' . strtolower(substr($this->orderNumber, 4)),
            'label_available',
        );

        self::assertCount(1, $shipments);
        self::assertSame($this->orderNumber, $shipments[0]['order_number']);
        self::assertSame('GLS', $shipments[0]['provider']);
        self::assertSame(1, $shipments[0]['package_detail_count']);
        self::assertSame(1200, $shipments[0]['package_weight_grams']);
        self::assertSame(1, $shipments[0]['label_count']);
        self::assertSame('PDF', $shipments[0]['latest_label_format']);
    }

    private function seed(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(5)));
        $this->orderNumber = 'ORD-' . $suffix;
        $this->marketplaceId = $this->insertAndReturnId(
            'INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
             VALUES (:code, :name, :adapter, TRUE, :business_status, NOW(), NOW()) RETURNING id',
            ['code' => 'read-' . strtolower($suffix), 'name' => 'Read model market', 'adapter' => 'test', 'business_status' => 'pilot'],
        );
        $this->customerId = $this->insertAndReturnId(
            'INSERT INTO customers (customer_code, status, customer_type, display_name, email, email_normalized)
             VALUES (:code, :status, :type, :name, :email, :email) RETURNING id',
            [
                'code' => 'CUST-' . $suffix,
                'status' => 'active',
                'type' => 'person',
                'name' => 'Cliente read model',
                'email' => 'read-' . strtolower($suffix) . '@example.test',
            ],
        );
        $this->orderId = $this->insertAndReturnId(
            <<<'SQL'
INSERT INTO orders (
    marketplace_id, customer_id, order_number, external_order_id, status,
    currency, shipping_address, billing_address, placed_at, version,
    subtotal_minor, shipping_total_minor, discount_total_minor, tax_total_minor,
    grand_total_minor, created_at, updated_at
) VALUES (
    :marketplace_id, :customer_id, :order_number, :external_order_id, :status,
    'EUR', CAST(:shipping_address AS JSONB), CAST(:billing_address AS JSONB), NOW(), 2,
    999, 300, 0, 0, 1299, NOW(), NOW()
) RETURNING id
SQL,
            [
                'marketplace_id' => $this->marketplaceId,
                'customer_id' => $this->customerId,
                'order_number' => $this->orderNumber,
                'external_order_id' => 'external-' . strtolower($suffix),
                'status' => 'waiting_goods',
                'shipping_address' => '{"recipient":"Mario Rossi","address_line1":"Via Roma 1","postal_code":"00100","city":"Roma","country_code":"IT"}',
                'billing_address' => '{"recipient":"Mario Rossi","address_line1":"Via Roma 1","postal_code":"00100","city":"Roma","country_code":"IT"}',
            ],
        );
        $lineId = $this->insertAndReturnId(
            <<<'SQL'
INSERT INTO order_lines (
    order_id, line_number, sku, ean, quantity_ordered, quantity_available,
    quantity_to_ship, quantity_to_cancel, description_snapshot,
    unit_price_minor, line_total_minor, created_at, updated_at
) VALUES (
    :order_id, 1, :sku, :ean, 1, 1, 1, 0, :description, 999, 999, NOW(), NOW()
) RETURNING id
SQL,
            [
                'order_id' => $this->orderId,
                'sku' => 'SKU-' . $suffix,
                'ean' => '9781234567890',
                'description' => 'Articolo di prova',
            ],
        );
        $transition = $this->pdo->prepare(
            'INSERT INTO order_transitions (order_id, from_status, to_status, version, occurred_at)
             VALUES (:order_id, :from_status, :to_status, 2, NOW())',
        );
        $transition->execute(['order_id' => $this->orderId, 'from_status' => 'imported', 'to_status' => 'sent_to_space']);
        $supplierStatement = $this->pdo->query("SELECT id FROM suppliers WHERE code = 'space'");
        self::assertNotFalse($supplierStatement);
        $supplierId = (int) $supplierStatement->fetchColumn();
        $this->purchaseId = $this->insertAndReturnId(
            'INSERT INTO supplier_purchase_orders (
                order_id, supplier_id, purchase_number, status, currency,
                subtotal_minor, grand_total_minor, version, created_at, updated_at
             ) VALUES (
                :order_id, :supplier_id, :purchase_number, :status, :currency,
                700, 700, 1, NOW(), NOW()
             ) RETURNING id',
            [
                'order_id' => $this->orderId,
                'supplier_id' => $supplierId,
                'purchase_number' => 'PO-' . $suffix,
                'status' => 'requested',
                'currency' => 'EUR',
            ],
        );
        $purchaseLine = $this->pdo->prepare(
            'INSERT INTO supplier_purchase_order_lines (
                purchase_order_id, order_line_id, line_number, supplier_sku, quantity, unit_cost_minor, currency
             ) VALUES (:purchase_id, :order_line_id, 1, :sku, 1, 700, :currency)',
        );
        $purchaseLine->execute(['purchase_id' => $this->purchaseId, 'order_line_id' => $lineId, 'sku' => 'SPACE-' . $suffix, 'currency' => 'EUR']);
        $this->shipmentId = $this->insertAndReturnId(
            'INSERT INTO shipments (
                order_id, provider, external_shipment_id, tracking_number, status, packages, weight_kg, created_at, updated_at
             ) VALUES (
                :order_id, :provider, :external_id, :tracking, :status, 1, 1.200, NOW(), NOW()
             ) RETURNING id',
            [
                'order_id' => $this->orderId,
                'provider' => 'GLS',
                'external_id' => 'shipment-' . strtolower($suffix),
                'tracking' => 'track-' . strtolower($suffix),
                'status' => 'label_available',
            ],
        );
        $package = $this->pdo->prepare(
            'INSERT INTO shipment_packages (shipment_id, package_number, weight_grams)
             VALUES (:shipment_id, 1, 1200)',
        );
        $package->execute(['shipment_id' => $this->shipmentId]);
        $label = $this->pdo->prepare(
            'INSERT INTO shipment_labels (
                shipment_id, format, storage_reference, checksum_sha256, generated_at
             ) VALUES (:shipment_id, :format, :storage_reference, :checksum, NOW())',
        );
        $label->execute([
            'shipment_id' => $this->shipmentId,
            'format' => 'PDF',
            'storage_reference' => 'labels/' . strtolower($suffix) . '.pdf',
            'checksum' => str_repeat('a', 64),
        ]);
        $legacy = $this->pdo->prepare(
            'INSERT INTO legacy_external_deliveries (
                order_id, provider, operation, idempotency_key, request_payload,
                response_payload, http_status, status, attempt, correlation_id,
                error_message, created_at, updated_at
             ) VALUES (
                :order_id, :provider, :operation, :idempotency_key, CAST(:request AS JSONB),
                CAST(:response AS JSONB), 202, :status, 1, :correlation_id,
                :error_message, NOW(), NOW()
             )',
        );
        $legacy->execute([
            'order_id' => $this->orderId,
            'provider' => 'space',
            'operation' => 'purchase.create',
            'idempotency_key' => 'legacy-' . strtolower($suffix),
            'request' => '{"token":"must-not-leak"}',
            'response' => '{"secret":"must-not-leak"}',
            'status' => 'success',
            'correlation_id' => 'correlation-' . strtolower($suffix),
            'error_message' => 'sensitive detail',
        ]);
    }

    private function cleanup(): void
    {
        if (!isset($this->pdo)) {
            return;
        }
        if (isset($this->shipmentId)) {
            $this->delete('shipment_labels', 'shipment_id', $this->shipmentId);
            $this->delete('shipment_packages', 'shipment_id', $this->shipmentId);
        }
        if (isset($this->purchaseId)) {
            $this->delete('supplier_purchase_order_lines', 'purchase_order_id', $this->purchaseId);
        }
        if (isset($this->orderId)) {
            $this->delete('legacy_external_deliveries', 'order_id', $this->orderId);
            $this->delete('shipments', 'order_id', $this->orderId);
            $this->delete('supplier_purchase_orders', 'order_id', $this->orderId);
            $this->delete('order_transitions', 'order_id', $this->orderId);
            $this->delete('order_lines', 'order_id', $this->orderId);
            $this->delete('orders', 'id', $this->orderId);
        }
        if (isset($this->customerId)) {
            $this->delete('customers', 'id', $this->customerId);
        }
        if (isset($this->marketplaceId)) {
            $this->delete('marketplaces', 'id', $this->marketplaceId);
        }
    }

    /** @param array<string, int|string> $parameters */
    private function insertAndReturnId(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function delete(string $table, string $column, int $id): void
    {
        $statement = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s = :id', $table, $column));
        $statement->execute(['id' => $id]);
    }
}
