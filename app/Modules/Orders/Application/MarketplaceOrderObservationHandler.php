<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Application;

use DateTimeImmutable;
use Hapa\Core\Database\TransactionManager;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Marketplace\Contract\MarketplaceOrderObservation;
use Hapa\Modules\Orders\Application\OrderRepository;
use Hapa\Modules\Orders\Domain\Order;
use Hapa\Modules\Orders\Domain\OrderAddress;
use Hapa\Modules\Orders\Domain\OrderLine;
use Hapa\Modules\Orders\Domain\OrderNumber;
use Hapa\Modules\Orders\Domain\OrderStateMachine;
use Hapa\Modules\Orders\Domain\OrderStatus;
use PDO;
use RuntimeException;

final readonly class MarketplaceOrderObservationHandler
{
    public function __construct(
        private PDO $pdo,
        private TransactionManager $transactions,
        private OrderRepository $orders,
    ) {
    }

    public function handle(MessageEnvelope $message): MarketplaceOrderIngestionResult
    {
        $observation = MarketplaceOrderObservation::fromEnvelope($message);

        return $this->transactions->transactional(
            fn (): MarketplaceOrderIngestionResult => $this->ingest($observation),
        );
    }

    private function ingest(MarketplaceOrderObservation $observation): MarketplaceOrderIngestionResult
    {
        [$marketplaceId, $marketplaceAccountId] = $this->resolveAccount($observation);
        $this->lockIdentity($marketplaceAccountId, $observation->providerOrderId);
        $observationId = $this->reserveObservation($marketplaceAccountId, $observation);
        if ($observationId === null) {
            return $this->duplicateResult($marketplaceAccountId, $observation);
        }

        $existing = $this->existingOrder($marketplaceAccountId, $observation->providerOrderId);
        if ($existing !== null && new DateTimeImmutable((string) $existing['source_modified_at']) >= $observation->modifiedAt) {
            return $this->finish(
                $observationId,
                (int) $existing['id'],
                'ignored',
                'ignored_stale',
                'Versione SellRapido non successiva a quella già applicata.',
            );
        }

        $customerId = $this->upsertCustomer($observation);
        if ($existing === null) {
            $orderId = $this->createOrder(
                $marketplaceId,
                $marketplaceAccountId,
                $customerId,
                $observation,
            );
            $outcome = 'created';
        } else {
            $orderId = (int) $existing['id'];
            $this->updateOrder($orderId, $customerId, $observation);
            $outcome = 'updated';
        }

        return $this->finish($observationId, $orderId, 'applied', $outcome);
    }

    /** @return array{0: int, 1: int} */
    private function resolveAccount(MarketplaceOrderObservation $observation): array
    {
        $integration = $this->pdo->prepare(<<<'SQL'
SELECT configuration_version, desired_status, display_name
FROM integration_accounts
WHERE code = :code AND provider_code = 'sellrapido' AND desired_status <> 'retired'
FOR SHARE
SQL);
        $integration->execute(['code' => $observation->integrationAccountCode]);
        $accountConfiguration = $integration->fetch(PDO::FETCH_ASSOC);
        if (!is_array($accountConfiguration)) {
            throw new RuntimeException('Account SellRapido HAPA non configurato.');
        }

        $marketplace = $this->pdo->prepare(
            'SELECT id FROM marketplaces WHERE lower(code) = :code FOR SHARE',
        );
        $marketplace->execute(['code' => $observation->marketplaceCode]);
        $marketplaceId = $marketplace->fetchColumn();
        if ($marketplaceId === false) {
            throw new RuntimeException('Marketplace SellRapido non censito in HAPA.');
        }

        $existing = $this->pdo->prepare(
            'SELECT id, marketplace_id, connector_code FROM marketplace_accounts WHERE code = :code FOR UPDATE',
        );
        $existing->execute(['code' => $observation->integrationAccountCode]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            if ((int) $row['marketplace_id'] !== (int) $marketplaceId || $row['connector_code'] !== 'sellrapido') {
                throw new RuntimeException('Account SellRapido associato a un marketplace differente.');
            }
            $update = $this->pdo->prepare(<<<'SQL'
UPDATE marketplace_accounts
SET display_name = :display_name, status = :status,
    technical_enabled = :technical_enabled, version = :version, updated_at = NOW()
WHERE id = :id
SQL);
            $update->execute([
                'display_name' => (string) $accountConfiguration['display_name'],
                'status' => (string) $accountConfiguration['desired_status'],
                'technical_enabled' => in_array($accountConfiguration['desired_status'], ['pilot', 'active'], true),
                'version' => (int) $accountConfiguration['configuration_version'],
                'id' => (int) $row['id'],
            ]);

            return [(int) $marketplaceId, (int) $row['id']];
        }

        $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO marketplace_accounts (
    marketplace_id, code, display_name, connector_code, status,
    technical_enabled, version, created_at, updated_at
) VALUES (
    :marketplace_id, :code, :display_name, 'sellrapido', :status,
    :technical_enabled, :version, NOW(), NOW()
)
RETURNING id
SQL);
        $insert->execute([
            'marketplace_id' => (int) $marketplaceId,
            'code' => $observation->integrationAccountCode,
            'display_name' => (string) $accountConfiguration['display_name'],
            'status' => (string) $accountConfiguration['desired_status'],
            'technical_enabled' => in_array($accountConfiguration['desired_status'], ['pilot', 'active'], true),
            'version' => (int) $accountConfiguration['configuration_version'],
        ]);
        $id = $insert->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Creazione account marketplace SellRapido fallita.');
        }

        return [(int) $marketplaceId, (int) $id];
    }

    private function lockIdentity(int $accountId, string $providerOrderId): void
    {
        $statement = $this->pdo->prepare('SELECT pg_advisory_xact_lock(hashtextextended(:identity, 0))');
        $statement->execute(['identity' => $accountId . ':' . $providerOrderId]);
    }

    private function reserveObservation(int $accountId, MarketplaceOrderObservation $observation): ?int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO marketplace_order_observations (
    message_id, marketplace_account_id, provider_order_id, source_version,
    status, modified_at, observed_at, created_at
) VALUES (
    :message_id, :marketplace_account_id, :provider_order_id, :source_version,
    'processing', :modified_at, :observed_at, NOW()
)
ON CONFLICT DO NOTHING
RETURNING id
SQL);
        $statement->execute([
            'message_id' => $observation->messageId,
            'marketplace_account_id' => $accountId,
            'provider_order_id' => $observation->providerOrderId,
            'source_version' => $observation->sourceVersion,
            'modified_at' => $this->timestamp($observation->modifiedAt),
            'observed_at' => $this->timestamp($observation->observedAt),
        ]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function duplicateResult(
        int $accountId,
        MarketplaceOrderObservation $observation,
    ): MarketplaceOrderIngestionResult {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT observation.id, observation.order_id, observation.outcome
FROM marketplace_order_observations observation
WHERE observation.message_id = :message_id
   OR (observation.marketplace_account_id = :account_id
       AND observation.provider_order_id = :provider_order_id
       AND observation.source_version = :source_version)
ORDER BY observation.id
LIMIT 1
SQL);
        $statement->execute([
            'message_id' => $observation->messageId,
            'account_id' => $accountId,
            'provider_order_id' => $observation->providerOrderId,
            'source_version' => $observation->sourceVersion,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Osservazione ordine SellRapido duplicata non recuperabile.');
        }

        return new MarketplaceOrderIngestionResult(
            (int) $row['id'],
            $row['order_id'] === null ? null : (int) $row['order_id'],
            'duplicate',
            is_string($row['outcome']) ? 'Esito originale: ' . $row['outcome'] : null,
        );
    }

    /** @return array<string, mixed>|null */
    private function existingOrder(int $accountId, string $providerOrderId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, order_number, status, version, source_modified_at
FROM orders
WHERE marketplace_account_id = :account_id AND provider_order_id = :provider_order_id
FOR UPDATE
SQL);
        $statement->execute(['account_id' => $accountId, 'provider_order_id' => $providerOrderId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function createOrder(
        int $marketplaceId,
        int $marketplaceAccountId,
        int $customerId,
        MarketplaceOrderObservation $observation,
    ): int {
        $number = new OrderNumber('SR-' . strtoupper(substr(hash(
            'sha256',
            $observation->integrationAccountCode . ':' . $observation->providerOrderId,
        ), 0, 28)));
        $lines = array_map(
            static fn (array $row, int $index): OrderLine => new OrderLine(
                $index + 1,
                $row['sku'],
                $row['provider_row_id'],
                $row['ean'],
                $row['quantity'],
            ),
            $observation->rows,
            array_keys($observation->rows),
        );
        $order = Order::marketplace(
            $number,
            $marketplaceId,
            $observation->externalOrderId,
            $observation->currency,
            $observation->orderedAt,
            ...$lines,
        );
        $this->orders->save($order, 0);
        $expectedVersion = 1;
        $order->attachShippingAddress($this->orderAddress($observation), $observation->modifiedAt);
        $this->orders->save($order, $expectedVersion);
        $expectedVersion = $order->version();
        if ($observation->providerStatus === 'cancelled') {
            $order->cancel('Cancellazione osservata da SellRapido.', $observation->observedAt);
            $this->orders->save($order, $expectedVersion);
        }

        $statement = $this->pdo->prepare('SELECT id FROM orders WHERE order_number = :number');
        $statement->execute(['number' => (string) $number]);
        $orderId = $statement->fetchColumn();
        if ($orderId === false) {
            throw new RuntimeException('Ordine SellRapido creato ma non recuperabile.');
        }
        $this->decorateOrder((int) $orderId, $marketplaceAccountId, $customerId, $observation, false);

        return (int) $orderId;
    }

    private function updateOrder(
        int $orderId,
        int $customerId,
        MarketplaceOrderObservation $observation,
    ): void {
        if ($observation->providerStatus !== 'cancelled') {
            $this->decorateOrder($orderId, null, $customerId, $observation, true);
            return;
        }

        $statement = $this->pdo->prepare('SELECT order_number FROM orders WHERE id = :id');
        $statement->execute(['id' => $orderId]);
        $number = $statement->fetchColumn();
        if (!is_string($number)) {
            throw new RuntimeException('Ordine SellRapido non recuperabile per la cancellazione.');
        }
        $order = $this->orders->find(new OrderNumber($number));
        if ($order === null || OrderStateMachine::isTerminal($order->status())) {
            $this->decorateOrder($orderId, null, $customerId, $observation, true);
            return;
        }
        $expectedVersion = $order->version();
        if (in_array($order->status(), [OrderStatus::Imported, OrderStatus::Accepted, OrderStatus::WaitingAddress], true)) {
            $order->cancel('Cancellazione osservata da SellRapido.', $observation->observedAt);
        } else {
            $order->placeInManualReview('Cancellazione SellRapido successiva all\'avanzamento operativo.', $observation->observedAt);
        }
        $this->orders->save($order, $expectedVersion);
        $this->decorateOrder($orderId, null, $customerId, $observation, false);
    }

    private function decorateOrder(
        int $orderId,
        ?int $marketplaceAccountId,
        int $customerId,
        MarketplaceOrderObservation $observation,
        bool $incrementVersion,
    ): void {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE orders
SET marketplace_account_id = COALESCE(:marketplace_account_id, marketplace_account_id),
    customer_id = :customer_id,
    external_order_id = :external_order_id,
    provider_order_id = :provider_order_id,
    provider_status = :provider_status,
    source_version = :source_version,
    source_modified_at = :source_modified_at,
    source_observed_at = :source_observed_at,
    marketplace_code = :marketplace_code,
    channel_code = :channel_code,
    currency = :currency,
    shipping_address = CAST(:shipping_address AS JSONB),
    placed_at = :placed_at,
    subtotal_minor = :subtotal_minor,
    shipping_total_minor = :shipping_total_minor,
    tax_total_minor = :tax_total_minor,
    grand_total_minor = :grand_total_minor,
    marketplace_fee_total_minor = :marketplace_fee_total_minor,
    version = version + :version_increment,
    updated_at = :updated_at
WHERE id = :id
SQL);
        $statement->execute([
            'marketplace_account_id' => $marketplaceAccountId,
            'customer_id' => $customerId,
            'external_order_id' => $observation->externalOrderId,
            'provider_order_id' => $observation->providerOrderId,
            'provider_status' => $observation->providerStatus,
            'source_version' => $observation->sourceVersion,
            'source_modified_at' => $this->timestamp($observation->modifiedAt),
            'source_observed_at' => $this->timestamp($observation->observedAt),
            'marketplace_code' => $observation->marketplaceCode,
            'channel_code' => $observation->channelCode,
            'currency' => $observation->currency,
            'shipping_address' => json_encode($observation->shippingAddress, JSON_THROW_ON_ERROR),
            'placed_at' => $this->timestamp($observation->orderedAt),
            'subtotal_minor' => max(0, $observation->totals['order_minor'] - $observation->totals['shipping_minor']),
            'shipping_total_minor' => $observation->totals['shipping_minor'],
            'tax_total_minor' => $observation->totals['tax_minor'],
            'grand_total_minor' => $observation->totals['order_minor'],
            'marketplace_fee_total_minor' => $observation->totals['marketplace_fee_minor'],
            'version_increment' => $incrementVersion ? 1 : 0,
            'updated_at' => $this->timestamp($observation->observedAt),
            'id' => $orderId,
        ]);
        $this->replaceLineSnapshots($orderId, $observation);
    }

    private function replaceLineSnapshots(int $orderId, MarketplaceOrderObservation $observation): void
    {
        $delete = $this->pdo->prepare('DELETE FROM order_lines WHERE order_id = :order_id');
        $delete->execute(['order_id' => $orderId]);
        $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO order_lines (
    order_id, line_number, sku, external_line_id, ean, quantity_ordered,
    quantity_available, quantity_to_ship, quantity_to_cancel,
    description_snapshot, unit_price_minor, tax_rate_basis_points,
    discount_total_minor, line_total_minor, created_at, updated_at
) VALUES (
    :order_id, :line_number, :sku, :external_line_id, :ean, :quantity_ordered,
    0, 0, 0, :description_snapshot, :unit_price_minor, :tax_rate_basis_points,
    0, :line_total_minor, :created_at, :updated_at
)
SQL);
        foreach ($observation->rows as $index => $row) {
            $insert->execute([
                'order_id' => $orderId,
                'line_number' => $index + 1,
                'sku' => $row['sku'],
                'external_line_id' => $row['provider_row_id'],
                'ean' => $row['ean'],
                'quantity_ordered' => $row['quantity'],
                'description_snapshot' => $row['title'],
                'unit_price_minor' => $row['unit_price_minor'],
                'tax_rate_basis_points' => $row['tax_rate_basis_points'],
                'line_total_minor' => $row['total_price_minor'] + $row['shipping_minor'],
                'created_at' => $this->timestamp($observation->orderedAt),
                'updated_at' => $this->timestamp($observation->observedAt),
            ]);
        }
    }

    private function upsertCustomer(MarketplaceOrderObservation $observation): int
    {
        $externalCustomerId = $observation->customer['external_customer_id'] ?? $observation->providerOrderId;
        $identity = $this->pdo->prepare(<<<'SQL'
SELECT customer_id
FROM customer_external_identities
WHERE source = :source AND account_reference = :account_reference
  AND external_customer_id = :external_customer_id
FOR UPDATE
SQL);
        $identity->execute([
            'source' => $observation->marketplaceCode,
            'account_reference' => $observation->integrationAccountCode,
            'external_customer_id' => $externalCustomerId,
        ]);
        $customerId = $identity->fetchColumn();
        if ($customerId === false) {
            $customerId = $this->createCustomer($observation);
            $insertIdentity = $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_external_identities (
    customer_id, source, account_reference, external_customer_id, created_at, updated_at
) VALUES (
    :customer_id, :source, :account_reference, :external_customer_id, NOW(), NOW()
)
SQL);
            $insertIdentity->execute([
                'customer_id' => $customerId,
                'source' => $observation->marketplaceCode,
                'account_reference' => $observation->integrationAccountCode,
                'external_customer_id' => $externalCustomerId,
            ]);
        } else {
            $this->updateCustomer((int) $customerId, $observation);
        }
        $this->upsertShippingAddress((int) $customerId, $observation);

        return (int) $customerId;
    }

    private function createCustomer(MarketplaceOrderObservation $observation): int
    {
        $code = 'CUS-SR-' . strtoupper(substr(hash(
            'sha256',
            $observation->integrationAccountCode . ':'
                . ($observation->customer['external_customer_id'] ?? $observation->providerOrderId),
        ), 0, 24));
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO customers (
    customer_code, status, customer_type, display_name, company_name, email, email_normalized,
    phone, tax_identifier, vat_number, locale, version, created_at, updated_at
) VALUES (
    :customer_code, 'active', :customer_type, :display_name, :company_name, :email, :email_normalized,
    :phone, :tax_identifier, :vat_number, 'it-IT', 1, NOW(), NOW()
)
RETURNING id
SQL);
        $statement->execute([
            'customer_code' => $code,
            'customer_type' => $observation->customer['vat_number'] === null ? 'person' : 'business',
            'display_name' => $observation->customer['name'],
            'company_name' => $observation->customer['vat_number'] === null ? null : $observation->customer['name'],
            'email' => $observation->customer['email'],
            'email_normalized' => $observation->customer['email'] === null
                ? null
                : strtolower($observation->customer['email']),
            'phone' => $observation->customer['phone'],
            'tax_identifier' => $observation->customer['fiscal_code'],
            'vat_number' => $observation->customer['vat_number'],
        ]);
        $customerId = $statement->fetchColumn();
        if ($customerId === false) {
            throw new RuntimeException('Creazione cliente SellRapido fallita.');
        }
        $this->appendCustomerHistory((int) $customerId, 1, 'marketplace_imported', $observation);

        return (int) $customerId;
    }

    private function updateCustomer(int $customerId, MarketplaceOrderObservation $observation): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE customers
SET display_name = :display_name,
    company_name = :company_name,
    email = :email,
    email_normalized = :email_normalized,
    phone = :phone,
    tax_identifier = :tax_identifier,
    vat_number = :vat_number,
    customer_type = :customer_type,
    version = version + 1,
    updated_at = NOW()
WHERE id = :id
RETURNING version
SQL);
        $statement->execute([
            'display_name' => $observation->customer['name'],
            'company_name' => $observation->customer['vat_number'] === null ? null : $observation->customer['name'],
            'email' => $observation->customer['email'],
            'email_normalized' => $observation->customer['email'] === null
                ? null
                : strtolower($observation->customer['email']),
            'phone' => $observation->customer['phone'],
            'tax_identifier' => $observation->customer['fiscal_code'],
            'vat_number' => $observation->customer['vat_number'],
            'customer_type' => $observation->customer['vat_number'] === null ? 'person' : 'business',
            'id' => $customerId,
        ]);
        $version = $statement->fetchColumn();
        if ($version === false) {
            throw new RuntimeException('Aggiornamento cliente SellRapido fallito.');
        }
        $this->appendCustomerHistory($customerId, (int) $version, 'marketplace_observed', $observation);
    }

    private function upsertShippingAddress(int $customerId, MarketplaceOrderObservation $observation): void
    {
        $address = $observation->shippingAddress;
        $update = $this->pdo->prepare(<<<'SQL'
UPDATE customer_addresses
SET recipient = :recipient, address_line1 = :address_line1, address_line2 = :address_line2,
    postal_code = :postal_code, city = :city, province = :province,
    country_code = :country_code, phone = :phone, active = TRUE, updated_at = NOW()
WHERE customer_id = :customer_id AND is_default_shipping
SQL);
        $parameters = [
            'customer_id' => $customerId,
            'recipient' => $address['recipient'],
            'address_line1' => $address['address_line1'],
            'address_line2' => $address['address_line2'],
            'postal_code' => $address['postal_code'],
            'city' => $address['city'],
            'province' => $address['province'],
            'country_code' => $address['country_code'],
            'phone' => $address['phone'],
        ];
        $update->execute($parameters);
        if ($update->rowCount() > 0) {
            return;
        }
        $insert = $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_addresses (
    customer_id, label, recipient, address_line1, address_line2, postal_code,
    city, province, country_code, phone, active, is_default_shipping,
    is_default_billing, created_at, updated_at
) VALUES (
    :customer_id, 'Marketplace', :recipient, :address_line1, :address_line2, :postal_code,
    :city, :province, :country_code, :phone, TRUE, TRUE, FALSE, NOW(), NOW()
)
SQL);
        $insert->execute($parameters);
    }

    private function appendCustomerHistory(
        int $customerId,
        int $version,
        string $changeType,
        MarketplaceOrderObservation $observation,
    ): void {
        $snapshot = [
            'display_name' => $observation->customer['name'],
            'email' => $observation->customer['email'],
            'phone' => $observation->customer['phone'],
            'tax_identifier' => $observation->customer['fiscal_code'],
            'vat_number' => $observation->customer['vat_number'],
            'source' => $observation->marketplaceCode,
            'account_reference' => $observation->integrationAccountCode,
        ];
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_history (
    customer_id, version, change_type, snapshot, actor_id,
    correlation_id, occurred_at, created_at
) VALUES (
    :customer_id, :version, :change_type, CAST(:snapshot AS JSONB), NULL,
    :correlation_id, :occurred_at, NOW()
)
SQL);
        $statement->execute([
            'customer_id' => $customerId,
            'version' => $version,
            'change_type' => $changeType,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'correlation_id' => $observation->correlationId,
            'occurred_at' => $this->timestamp($observation->observedAt),
        ]);
    }

    private function orderAddress(MarketplaceOrderObservation $observation): OrderAddress
    {
        $address = $observation->shippingAddress;

        return new OrderAddress(
            $address['recipient'],
            $address['address_line1'],
            $address['address_line2'],
            $address['postal_code'],
            $address['city'],
            $address['province'],
            $address['country_code'],
            $address['phone'],
        );
    }

    private function finish(
        int $observationId,
        ?int $orderId,
        string $status,
        string $outcome,
        ?string $reason = null,
    ): MarketplaceOrderIngestionResult {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE marketplace_order_observations
SET order_id = :order_id, status = :status, outcome = :outcome,
    reason = :reason, processed_at = NOW()
WHERE id = :id AND status = 'processing'
SQL);
        $statement->execute([
            'order_id' => $orderId,
            'status' => $status,
            'outcome' => $outcome,
            'reason' => $reason,
            'id' => $observationId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Finalizzazione osservazione ordine SellRapido fallita.');
        }

        return new MarketplaceOrderIngestionResult($observationId, $orderId, $outcome, $reason);
    }

    private function timestamp(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.uP');
    }
}
