<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Modules\Customers\Domain\CustomerIdentitySource;
use Hapa\Modules\Customers\Domain\CustomerStatus;
use Hapa\Modules\Customers\Domain\CustomerType;
use Hapa\Modules\Orders\Domain\OrderOrigin;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CustomerOrderMasterDataTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
            $this->pdo->beginTransaction();
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testEmailSupportsSearchWithoutUnsafeAutomaticDeduplication(): void
    {
        $email = 'shared-' . bin2hex(random_bytes(4)) . '@example.com';
        $first = $this->createCustomer(bin2hex(random_bytes(4)), $email);
        $second = $this->createCustomer(bin2hex(random_bytes(4)), $email);

        self::assertNotSame($first, $second);

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM customers WHERE email_normalized = :email');
        $statement->execute(['email' => strtolower($email)]);
        self::assertSame(2, (int) $statement->fetchColumn());
    }

    public function testDatabaseEnumerationsMatchDomainEnums(): void
    {
        $expectations = [
            'customers_status_check' => array_map(static fn (CustomerStatus $status): string => $status->value, CustomerStatus::cases()),
            'customers_type_check' => array_map(static fn (CustomerType $type): string => $type->value, CustomerType::cases()),
            'customer_external_identities_source_check' => array_map(
                static fn (CustomerIdentitySource $source): string => $source->value,
                CustomerIdentitySource::cases(),
            ),
            'orders_origin_check' => array_map(static fn (OrderOrigin $origin): string => $origin->value, OrderOrigin::cases()),
        ];

        foreach ($expectations as $constraint => $domainValues) {
            $databaseValues = $this->constraintValues($constraint);
            sort($databaseValues);
            sort($domainValues);
            self::assertSame($domainValues, $databaseValues, sprintf('Vincolo %s non allineato al dominio.', $constraint));
        }
    }

    public function testExternalIdentityIsUniquePerSourceAccountAndIdentifier(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $firstCustomerId = $this->createCustomer($suffix . 'a');
        $secondCustomerId = $this->createCustomer($suffix . 'b');
        $sql = 'INSERT INTO customer_external_identities (
                    customer_id, source, account_reference, external_customer_id
                ) VALUES (
                    :customer_id, :source, :account_reference, :external_customer_id
                )';
        $identity = [
            'source' => 'amazon',
            'account_reference' => 'account-' . $suffix,
            'external_customer_id' => 'buyer-' . $suffix,
        ];

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['customer_id' => $firstCustomerId, ...$identity]);

        $this->expectException(PDOException::class);
        $statement->execute(['customer_id' => $secondCustomerId, ...$identity]);
    }

    public function testCustomerHasAtMostOneDefaultShippingAddress(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $customerId = $this->createCustomer($suffix);
        $sql = 'INSERT INTO customer_addresses (
                    customer_id, label, recipient, address_line1, postal_code,
                    city, country_code, is_default_shipping
                ) VALUES (
                    :customer_id, :label, :recipient, :address_line1, :postal_code,
                    :city, :country_code, TRUE
                )';
        $statement = $this->pdo->prepare($sql);
        $base = [
            'customer_id' => $customerId,
            'recipient' => 'Mario Rossi',
            'address_line1' => 'Via Roma 1',
            'postal_code' => '00100',
            'city' => 'Roma',
            'country_code' => 'IT',
        ];

        $statement->execute(['label' => 'Casa', ...$base]);

        $this->expectException(PDOException::class);
        $statement->execute(['label' => 'Ufficio', ...$base]);
    }

    public function testB2cOrderRequiresAStorefrontAndNoMarketplace(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $customerId = $this->createCustomer($suffix);
        $orderId = $this->insertAndReturnId(
            'INSERT INTO orders (
                customer_id, order_number, origin, origin_reference, external_order_id,
                status, currency, version, placed_at, created_at, updated_at
             ) VALUES (
                :customer_id, :order_number, :origin, :origin_reference, :external_order_id,
                :status, :currency, 1, NOW(), NOW(), NOW()
             ) RETURNING id',
            [
                'customer_id' => $customerId,
                'order_number' => 'B2C-' . strtoupper($suffix),
                'origin' => 'b2c_ecommerce',
                'origin_reference' => 'storefront-it',
                'external_order_id' => 'checkout-' . $suffix,
                'status' => 'new',
                'currency' => 'EUR',
            ],
        );

        self::assertGreaterThan(0, $orderId);
    }

    public function testOriginConstraintRejectsAMarketplaceOrderWithoutMarketplace(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $statement = $this->pdo->prepare(
            'INSERT INTO orders (
                order_number, origin, external_order_id, status, currency, version, created_at, updated_at
             ) VALUES (
                :order_number, :origin, :external_order_id, :status, :currency, 1, NOW(), NOW()
             )',
        );

        $this->expectException(PDOException::class);
        $statement->execute([
            'order_number' => 'ORD-' . strtoupper($suffix),
            'origin' => 'marketplace',
            'external_order_id' => 'order-' . $suffix,
            'status' => 'new',
            'currency' => 'EUR',
        ]);
    }

    public function testB2cExternalOrderIsUniqueWithinTheStorefront(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $sql = 'INSERT INTO orders (
                    order_number, origin, origin_reference, external_order_id,
                    status, currency, version, created_at, updated_at
                ) VALUES (
                    :order_number, :origin, :origin_reference, :external_order_id,
                    :status, :currency, 1, NOW(), NOW()
                )';
        $base = [
            'origin' => 'b2c_ecommerce',
            'origin_reference' => 'storefront-it',
            'external_order_id' => 'checkout-' . $suffix,
            'status' => 'new',
            'currency' => 'EUR',
        ];
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['order_number' => 'B2C-A-' . strtoupper($suffix), ...$base]);

        $this->expectException(PDOException::class);
        $statement->execute(['order_number' => 'B2C-B-' . strtoupper($suffix), ...$base]);
    }

    public function testDeletingCustomerKeepsTheHistoricalOrder(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $customerId = $this->createCustomer($suffix);
        $marketplaceId = $this->createMarketplace($suffix);
        $orderId = $this->insertAndReturnId(
            'INSERT INTO orders (
                marketplace_id, customer_id, order_number, external_order_id,
                status, currency, version, created_at, updated_at
             ) VALUES (
                :marketplace_id, :customer_id, :order_number, :external_order_id,
                :status, :currency, 1, NOW(), NOW()
             ) RETURNING id',
            [
                'marketplace_id' => $marketplaceId,
                'customer_id' => $customerId,
                'order_number' => 'ORD-' . strtoupper($suffix),
                'external_order_id' => 'order-' . $suffix,
                'status' => 'imported',
                'currency' => 'EUR',
            ],
        );

        $statement = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
        $statement->execute(['id' => $customerId]);

        $statement = $this->pdo->prepare('SELECT CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END FROM orders WHERE id = :id');
        $statement->execute(['id' => $orderId]);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    private function createCustomer(string $suffix, ?string $email = null): int
    {
        return $this->insertAndReturnId(
            'INSERT INTO customers (
                customer_code, status, customer_type, display_name, email, email_normalized
             ) VALUES (
                :customer_code, :status, :customer_type, :display_name, :email, :email_normalized
             ) RETURNING id',
            [
                'customer_code' => 'CUST-' . strtoupper($suffix),
                'status' => 'active',
                'customer_type' => 'person',
                'display_name' => 'Cliente ' . $suffix,
                'email' => $email,
                'email_normalized' => $email === null ? null : strtolower(trim($email)),
            ],
        );
    }

    private function createMarketplace(string $suffix): int
    {
        return $this->insertAndReturnId(
            'INSERT INTO marketplaces (code, name, adapter_key, active, created_at, updated_at)
             VALUES (:code, :name, :adapter, TRUE, NOW(), NOW()) RETURNING id',
            [
                'code' => 'market-' . $suffix,
                'name' => 'Test Marketplace',
                'adapter' => 'test',
            ],
        );
    }

    /** @return list<string> */
    private function constraintValues(string $constraint): array
    {
        $statement = $this->pdo->prepare(
            'SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = :constraint',
        );
        $statement->execute(['constraint' => $constraint]);
        $definition = $statement->fetchColumn();
        self::assertIsString($definition, sprintf('Vincolo %s non trovato.', $constraint));

        /** @var array<int, list<string>> $matches */
        $matches = [];
        preg_match_all("/'([^']+)'/", $definition, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param array<string, int|string|null> $parameters
     */
    private function insertAndReturnId(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }
}
