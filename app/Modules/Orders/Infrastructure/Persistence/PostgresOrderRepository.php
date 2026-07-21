<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Infrastructure\Persistence;

use DateTimeImmutable;
use Hapa\Core\Database\TransactionManager;
use Hapa\Core\Outbox\OutboxRepository;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\OrderRepository;
use Hapa\Modules\Orders\Domain\Order;
use Hapa\Modules\Orders\Domain\OrderAddress;
use Hapa\Modules\Orders\Domain\OrderLine;
use Hapa\Modules\Orders\Domain\OrderNumber;
use Hapa\Modules\Orders\Domain\OrderOrigin;
use Hapa\Modules\Orders\Domain\OrderStatus;
use Hapa\Modules\Orders\Domain\OrderTransition;
use Hapa\Modules\Orders\Domain\StaleOrderVersion;
use JsonException;
use PDO;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class PostgresOrderRepository implements OrderRepository
{
    private const DATABASE_TIMESTAMP = 'Y-m-d H:i:s.uP';

    public function __construct(
        private PDO $pdo,
        private TransactionManager $transactions,
        private OutboxRepository $outbox,
        private OrderEventOutboxMapper $eventMapper,
    ) {
    }

    public function find(OrderNumber $number): ?Order
    {
        $statement = $this->pdo->prepare('SELECT * FROM orders WHERE order_number = :order_number');
        $statement->execute(['order_number' => (string) $number]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        $orderId = (int) $row['id'];
        $lines = $this->loadLines($orderId);
        $transitions = $this->loadTransitions($orderId);
        $status = OrderStatus::from((string) $row['status']);
        $statusBeforeManualReview = null;
        if ($status === OrderStatus::ManualReview && $transitions !== []) {
            $statusBeforeManualReview = $transitions[array_key_last($transitions)]->from;
        }

        return Order::reconstitute([
            'number' => new OrderNumber((string) $row['order_number']), 'origin' => OrderOrigin::from((string) $row['origin']),
            'external_order_id' => (string) $row['external_order_id'], 'marketplace_id' => $row['marketplace_id'] === null ? null : (int) $row['marketplace_id'],
            'origin_reference' => $row['origin_reference'] === null ? null : (string) $row['origin_reference'], 'currency' => (string) $row['currency'],
            'status' => $status, 'version' => (int) $row['version'], 'lines' => $lines,
            'shipping_address' => $this->decodeAddress($row['shipping_address']), 'billing_address' => $this->decodeAddress($row['billing_address']),
            'last_occurred_at' => new DateTimeImmutable((string) $row['updated_at']), 'status_before_manual_review' => $statusBeforeManualReview,
            'transitions' => $transitions,
        ]);
    }

    public function save(Order $order, int $expectedVersion): void
    {
        if ($expectedVersion < 0 || ($expectedVersion === 0 && $order->version() !== 1)) {
            throw new StaleOrderVersion($expectedVersion, $order->version());
        }

        $events = $order->pendingEvents();
        $this->transactions->transactional(function () use ($order, $expectedVersion, $events): void {
            $orderId = $expectedVersion === 0
                ? $this->insertOrder($order)
                : $this->updateOrder($order, $expectedVersion);

            $this->replaceLines($orderId, $order);
            $this->appendTransitions($orderId, $order, $expectedVersion);

            foreach ($events as $event) {
                $this->outbox->append($this->eventMapper->map($event));
            }
        });
        $order->clearEvents();
    }

    private function insertOrder(Order $order): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO orders (
    marketplace_id, order_number, origin, origin_reference, external_order_id,
    status, currency, shipping_address, billing_address, placed_at, version,
    created_at, updated_at
) VALUES (
    :marketplace_id, :order_number, :origin, :origin_reference, :external_order_id,
    :status, :currency, CAST(:shipping_address AS JSONB), CAST(:billing_address AS JSONB),
    :placed_at, :version, :created_at, :updated_at
)
RETURNING id
SQL);
        $statement->execute($this->orderParameters($order));
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new HapaRuntimeException('Inserimento ordine non riuscito.');
        }

        return (int) $id;
    }

    private function updateOrder(Order $order, int $expectedVersion): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE orders
SET marketplace_id = :marketplace_id,
    origin = :origin,
    origin_reference = :origin_reference,
    external_order_id = :external_order_id,
    status = :status,
    currency = :currency,
    shipping_address = CAST(:shipping_address AS JSONB),
    billing_address = CAST(:billing_address AS JSONB),
    version = :version,
    updated_at = :updated_at
WHERE order_number = :order_number
  AND version = :expected_version
RETURNING id
SQL);
        $parameters = $this->orderParameters($order);
        unset($parameters['placed_at'], $parameters['created_at']);
        $statement->execute([...$parameters, 'expected_version' => $expectedVersion]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            $currentVersion = $this->currentVersion($order->number);
            throw new StaleOrderVersion($expectedVersion, $currentVersion ?? 0);
        }

        return (int) $id;
    }

    /** @return array<string, int|string|null> */
    private function orderParameters(Order $order): array
    {
        $occurredAt = $order->lastOccurredAt()->format(self::DATABASE_TIMESTAMP);

        return [
            'marketplace_id' => $order->marketplaceId,
            'order_number' => (string) $order->number,
            'origin' => $order->origin->value,
            'origin_reference' => $order->originReference,
            'external_order_id' => $order->externalOrderId,
            'status' => $order->status()->value,
            'currency' => $order->currency,
            'shipping_address' => $this->encodeAddress($order->shippingAddress()),
            'billing_address' => $this->encodeAddress($order->billingAddress()),
            'placed_at' => $occurredAt,
            'version' => $order->version(),
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ];
    }

    private function replaceLines(int $orderId, Order $order): void
    {
        $delete = $this->pdo->prepare('DELETE FROM order_lines WHERE order_id = :order_id');
        $delete->execute(['order_id' => $orderId]);
        $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO order_lines (
    order_id, line_number, sku, external_line_id, ean, quantity_ordered,
    quantity_available, quantity_to_ship, quantity_to_cancel, created_at, updated_at
) VALUES (
    :order_id, :line_number, :sku, :external_line_id, :ean, :quantity_ordered,
    :quantity_available, :quantity_to_ship, :quantity_to_cancel, :created_at, :updated_at
)
SQL);
        $timestamp = $order->lastOccurredAt()->format(self::DATABASE_TIMESTAMP);
        foreach ($order->lines() as $line) {
            $insert->execute([
                'order_id' => $orderId,
                'line_number' => $line->lineNumber,
                'sku' => $line->sku,
                'external_line_id' => $line->externalLineId,
                'ean' => $line->ean,
                'quantity_ordered' => $line->quantityOrdered,
                'quantity_available' => $line->quantityAvailable,
                'quantity_to_ship' => $line->quantityToShip,
                'quantity_to_cancel' => $line->quantityToCancel,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function appendTransitions(int $orderId, Order $order, int $expectedVersion): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO order_transitions (
    order_id, from_status, to_status, reason, version, occurred_at, created_at
) VALUES (
    :order_id, :from_status, :to_status, :reason, :version, :occurred_at, :created_at
)
ON CONFLICT (order_id, version) DO NOTHING
SQL);
        foreach ($order->transitions() as $transition) {
            if ($transition->version <= $expectedVersion) {
                continue;
            }

            $occurredAt = $transition->occurredAt->format(self::DATABASE_TIMESTAMP);
            $statement->execute([
                'order_id' => $orderId,
                'from_status' => $transition->from->value,
                'to_status' => $transition->to->value,
                'reason' => $transition->reason,
                'version' => $transition->version,
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);
        }
    }

    /** @return non-empty-list<OrderLine> */
    private function loadLines(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM order_lines WHERE order_id = :order_id ORDER BY line_number',
        );
        $statement->execute(['order_id' => $orderId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();
        if ($rows === []) {
            throw new HapaRuntimeException('L’ordine persistito non contiene righe.');
        }

        return array_map(static fn (array $row): OrderLine => new OrderLine(
            (int) $row['line_number'],
            (string) $row['sku'],
            $row['external_line_id'] === null ? null : (string) $row['external_line_id'],
            $row['ean'] === null ? null : (string) $row['ean'],
            (int) $row['quantity_ordered'],
            (int) $row['quantity_available'],
            (int) $row['quantity_to_ship'],
            (int) $row['quantity_to_cancel'],
        ), $rows);
    }

    /** @return list<OrderTransition> */
    private function loadTransitions(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM order_transitions WHERE order_id = :order_id ORDER BY version',
        );
        $statement->execute(['order_id' => $orderId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): OrderTransition => new OrderTransition(
            OrderStatus::from((string) $row['from_status']),
            OrderStatus::from((string) $row['to_status']),
            (int) $row['version'],
            new DateTimeImmutable((string) $row['occurred_at']),
            $row['reason'] === null ? null : (string) $row['reason'],
        ), $rows);
    }

    private function currentVersion(OrderNumber $number): ?int
    {
        $statement = $this->pdo->prepare('SELECT version FROM orders WHERE order_number = :order_number');
        $statement->execute(['order_number' => (string) $number]);
        $version = $statement->fetchColumn();

        return $version === false ? null : (int) $version;
    }

    private function encodeAddress(?OrderAddress $address): ?string
    {
        if ($address === null) {
            return null;
        }

        return json_encode([
            'recipient' => $address->recipient,
            'address_line1' => $address->addressLine1,
            'address_line2' => $address->addressLine2,
            'postal_code' => $address->postalCode,
            'city' => $address->city,
            'province' => $address->province,
            'country_code' => $address->countryCode,
            'phone' => $address->phone,
        ], JSON_THROW_ON_ERROR);
    }

    private function decodeAddress(mixed $value): ?OrderAddress
    {
        if ($value === null) {
            return null;
        }

        try {
            $data = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HapaRuntimeException('Snapshot indirizzo ordine non decodificabile.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new HapaRuntimeException('Snapshot indirizzo ordine non valido.');
        }

        return new OrderAddress([
            'recipient' => self::stringValue($data, 'recipient'), 'address_line1' => self::stringValue($data, 'address_line1'),
            'address_line2' => self::nullableStringValue($data, 'address_line2'), 'postal_code' => self::stringValue($data, 'postal_code'),
            'city' => self::stringValue($data, 'city'), 'province' => self::nullableStringValue($data, 'province'),
            'country_code' => self::stringValue($data, 'country_code'), 'phone' => self::nullableStringValue($data, 'phone'),
        ]);
    }

    /** @param array<mixed> $data */
    private static function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new HapaRuntimeException(sprintf('Campo indirizzo "%s" non valido.', $key));
        }

        return $value;
    }

    /** @param array<mixed> $data */
    private static function nullableStringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new HapaRuntimeException(sprintf('Campo indirizzo "%s" non valido.', $key));
        }

        return $value;
    }
}
