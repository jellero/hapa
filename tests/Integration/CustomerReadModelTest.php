<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Modules\Customers\Application\CustomerReadModel;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CustomerReadModelTest extends TestCase
{
    private PDO $pdo;
    private int $customerId;
    private string $customerCode;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $this->customerCode = 'TEST-' . strtoupper(bin2hex(random_bytes(5)));
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO customers (
    customer_code, status, customer_type, display_name, first_name, last_name,
    email, email_normalized, phone, locale, version
) VALUES (
    :customer_code, 'active', 'person', 'Cliente Test HAPA', 'Cliente', 'Test',
    'cliente.readmodel@example.test', 'cliente.readmodel@example.test', '+3900000000', 'it-IT', 1
)
RETURNING id
SQL);
            $statement->execute(['customer_code' => $this->customerCode]);
            $this->customerId = (int) $statement->fetchColumn();
            $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_external_identities (
    customer_id, source, account_reference, external_customer_id
) VALUES (:customer_id, 'ibs', 'sellrapido-primary', 'IBS-CUSTOMER-TEST')
SQL)->execute(['customer_id' => $this->customerId]);
            $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_addresses (
    customer_id, label, recipient, address_line1, postal_code, city,
    province, country_code, active, is_default_shipping, is_default_billing
) VALUES (
    :customer_id, 'Principale', 'Cliente Test HAPA', 'Via dei Test 1', '33100', 'Udine',
    'UD', 'IT', TRUE, TRUE, TRUE
)
SQL)->execute(['customer_id' => $this->customerId]);
            $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_history (
    customer_id, version, change_type, snapshot, actor_id, correlation_id, occurred_at
) VALUES (
    :customer_id, 1, 'created', '{"display_name":"Cliente Test HAPA"}'::jsonb,
    'integration-test', 'customer-read-model-test', NOW()
)
SQL)->execute(['customer_id' => $this->customerId]);

            $this->readModel = new CustomerReadModel($connections);
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    private CustomerReadModel $readModel;

    protected function tearDown(): void
    {
        if (!isset($this->pdo, $this->customerId)) {
            return;
        }
        $this->pdo->prepare('DELETE FROM customer_history WHERE customer_id = :id')->execute(['id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM customer_external_identities WHERE customer_id = :id')->execute(['id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM customer_addresses WHERE customer_id = :id')->execute(['id' => $this->customerId]);
        $this->pdo->prepare('DELETE FROM customers WHERE id = :id')->execute(['id' => $this->customerId]);
    }

    public function testItSearchesAndBuildsTheCompleteCustomerCard(): void
    {
        $customers = $this->readModel->search('IBS-CUSTOMER-TEST', 'active');
        self::assertCount(1, $customers);
        self::assertSame($this->customerCode, $customers[0]['customer_code']);
        self::assertSame('ibs', $customers[0]['identity_sources']);
        self::assertSame(1, $customers[0]['identity_count']);

        $customer = $this->readModel->detail(strtolower($this->customerCode));
        self::assertIsArray($customer);
        self::assertSame('Cliente Test HAPA', $customer['display_name']);
        self::assertCount(1, $customer['identities']);
        self::assertSame('IBS-CUSTOMER-TEST', $customer['identities'][0]['external_customer_id']);
        self::assertCount(1, $customer['addresses']);
        self::assertTrue($customer['addresses'][0]['is_default_shipping']);
        self::assertCount(1, $customer['history']);
        self::assertSame('created', $customer['history'][0]['change_type']);
        self::assertSame('Cliente Test HAPA', $customer['history'][0]['snapshot']['display_name']);
    }
}
