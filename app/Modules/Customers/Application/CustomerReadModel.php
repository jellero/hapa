<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Application;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Ui\CustomerOverview;
use JsonException;
use PDO;

final class CustomerReadModel implements CustomerOverview
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, string $status, int $limit = 100): array
    {
        $query = trim($query);
        $status = in_array($status, ['active', 'inactive', 'archived'], true) ? $status : '';
        $limit = max(1, min(200, $limit));
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT customer.id, customer.customer_code, customer.status, customer.customer_type,
       customer.display_name, customer.email, customer.phone, customer.version,
       customer.created_at, customer.updated_at,
       (SELECT COUNT(*) FROM customer_external_identities identity WHERE identity.customer_id = customer.id) AS identity_count,
       (SELECT string_agg(DISTINCT identity.source, ', ' ORDER BY identity.source)
          FROM customer_external_identities identity WHERE identity.customer_id = customer.id) AS identity_sources,
       (SELECT COUNT(*) FROM orders customer_order WHERE customer_order.customer_id = customer.id) AS order_count,
       (SELECT MAX(COALESCE(customer_order.placed_at, customer_order.created_at))
          FROM orders customer_order WHERE customer_order.customer_id = customer.id) AS last_order_at
FROM customers customer
WHERE (:status = '' OR customer.status = :status)
  AND (
      :query = ''
      OR customer.customer_code ILIKE :pattern
      OR customer.display_name ILIKE :pattern
      OR COALESCE(customer.email, '') ILIKE :pattern
      OR EXISTS (
          SELECT 1 FROM customer_external_identities identity
          WHERE identity.customer_id = customer.id
            AND (identity.external_customer_id ILIKE :pattern OR identity.account_reference ILIKE :pattern)
      )
  )
ORDER BY customer.updated_at DESC, customer.id DESC
LIMIT :limit
SQL);
        $statement->bindValue('status', $status);
        $statement->bindValue('query', $query);
        $statement->bindValue('pattern', '%' . $query . '%');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map(self::customer(...), $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /**
     * @return array<string, mixed>|null
     * @throws JsonException
     */
    public function detail(string $customerCode): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT customer.*,
       (SELECT COUNT(*) FROM orders customer_order WHERE customer_order.customer_id = customer.id) AS order_count,
       (SELECT MAX(COALESCE(customer_order.placed_at, customer_order.created_at))
          FROM orders customer_order WHERE customer_order.customer_id = customer.id) AS last_order_at
FROM customers customer
WHERE customer.customer_code = :customer_code
SQL);
        $statement->execute(['customer_code' => strtoupper(trim($customerCode))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $customer = self::customer($row);
        $customer['first_name'] = self::nullable($row['first_name']);
        $customer['last_name'] = self::nullable($row['last_name']);
        $customer['company_name'] = self::nullable($row['company_name']);
        $customer['tax_identifier'] = self::nullable($row['tax_identifier']);
        $customer['vat_number'] = self::nullable($row['vat_number']);
        $customer['locale'] = (string) $row['locale'];
        $customer['identities'] = $this->identities((int) $row['id']);
        $customer['addresses'] = $this->addresses((int) $row['id']);
        $customer['orders'] = $this->orders((int) $row['id']);
        $customer['history'] = $this->history((int) $row['id']);

        return $customer;
    }

    /** @return list<array<string, mixed>> */
    private function identities(int $customerId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT source, account_reference, external_customer_id, created_at, updated_at
FROM customer_external_identities
WHERE customer_id = :customer_id
ORDER BY source, account_reference, id
SQL);
        $statement->execute(['customer_id' => $customerId]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string, mixed>> */
    private function addresses(int $customerId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT label, recipient, address_line1, address_line2, postal_code, city,
       province, country_code, phone, active, is_default_shipping, is_default_billing
FROM customer_addresses
WHERE customer_id = :customer_id
ORDER BY active DESC, is_default_shipping DESC, is_default_billing DESC, id
SQL);
        $statement->execute(['customer_id' => $customerId]);
        $addresses = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['active'] = filter_var($row['active'], FILTER_VALIDATE_BOOL);
            $row['is_default_shipping'] = filter_var($row['is_default_shipping'], FILTER_VALIDATE_BOOL);
            $row['is_default_billing'] = filter_var($row['is_default_billing'], FILTER_VALIDATE_BOOL);
            $addresses[] = $row;
        }

        return $addresses;
    }

    /** @return list<array<string, mixed>> */
    private function orders(int $customerId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT customer_order.order_number, customer_order.origin, customer_order.status,
       customer_order.currency, customer_order.grand_total_minor,
       COALESCE(customer_order.placed_at, customer_order.created_at) AS ordered_at,
       marketplace.code AS marketplace_code
FROM orders customer_order
LEFT JOIN marketplaces marketplace ON marketplace.id = customer_order.marketplace_id
WHERE customer_order.customer_id = :customer_id
ORDER BY COALESCE(customer_order.placed_at, customer_order.created_at) DESC, customer_order.id DESC
LIMIT 100
SQL);
        $statement->execute(['customer_id' => $customerId]);
        $orders = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['grand_total_minor'] = $row['grand_total_minor'] === null ? null : (int) $row['grand_total_minor'];
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * @return list<array<string, mixed>>
     * @throws JsonException
     */
    private function history(int $customerId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT version, change_type, snapshot, actor_id, correlation_id, occurred_at
FROM customer_history
WHERE customer_id = :customer_id
ORDER BY occurred_at DESC, id DESC
LIMIT 100
SQL);
        $statement->execute(['customer_id' => $customerId]);
        $history = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $snapshot = json_decode((string) $row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
            $row['version'] = (int) $row['version'];
            $row['snapshot'] = is_array($snapshot) ? $snapshot : [];
            $history[] = $row;
        }

        return $history;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function customer(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'customer_code' => (string) $row['customer_code'],
            'status' => (string) $row['status'],
            'customer_type' => (string) $row['customer_type'],
            'display_name' => (string) $row['display_name'],
            'email' => self::nullable($row['email']),
            'phone' => self::nullable($row['phone']),
            'version' => (int) $row['version'],
            'identity_count' => (int) ($row['identity_count'] ?? 0),
            'identity_sources' => self::nullable($row['identity_sources'] ?? null),
            'order_count' => (int) ($row['order_count'] ?? 0),
            'last_order_at' => self::nullable($row['last_order_at'] ?? null),
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
