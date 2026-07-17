<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Logging\SensitiveDataRedactor;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\CustomerConflict;
use Hapa\Modules\Customers\Application\CustomerService;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CustomerServiceTest extends TestCase
{
    private PDO $pdo;
    private CustomerService $service;
    private string $customerCode;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $this->service = new CustomerService(
                $connections,
                new FrozenClock(new DateTimeImmutable('2026-07-17T20:30:00Z')),
                new SensitiveDataRedactor(),
            );
            $this->customerCode = 'CUST-' . strtoupper(bin2hex(random_bytes(5)));
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo, $this->customerCode)) {
            return;
        }
        $customerId = $this->customerId();
        $audit = $this->pdo->prepare("DELETE FROM audit_logs WHERE entity_type = 'customer' AND entity_id = :code");
        $audit->execute(['code' => $this->customerCode]);
        if ($customerId !== null) {
            $history = $this->pdo->prepare('DELETE FROM customer_history WHERE customer_id = :id');
            $history->execute(['id' => $customerId]);
            $customer = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
            $customer->execute(['id' => $customerId]);
        }
    }

    public function testCustomerLifecycleIsVersionedAuditedAndProtectedByOptimisticLocking(): void
    {
        $actor = new UserIdentity('admin-test', 'admin@example.test', 'Admin test', 'administrator');
        $createdCode = $this->service->create($this->input('Mario Rossi'), $actor, 'corr-create');

        self::assertSame($this->customerCode, $createdCode);
        $row = $this->customer();
        self::assertSame(1, (int) $row['version']);
        self::assertSame('mario@example.test', $row['email_normalized']);
        self::assertSame(['created'], $this->historyTypes());

        $this->service->update(
            $this->customerCode,
            1,
            [...$this->input('Mario R. aggiornato'), 'status' => 'inactive'],
            $actor,
            'corr-update',
        );
        $row = $this->customer();
        self::assertSame(2, (int) $row['version']);
        self::assertSame('inactive', $row['status']);
        self::assertSame(['created', 'updated'], $this->historyTypes());

        try {
            $this->service->update($this->customerCode, 1, $this->input('Versione obsoleta'), $actor, 'corr-stale');
            self::fail('Il lock ottimistico avrebbe dovuto rifiutare la versione obsoleta.');
        } catch (CustomerConflict) {
            self::assertSame(2, (int) $this->customer()['version']);
        }

        $this->service->archive($this->customerCode, 2, $actor, 'corr-archive');
        $row = $this->customer();
        self::assertSame(3, (int) $row['version']);
        self::assertSame('archived', $row['status']);
        self::assertSame(['created', 'updated', 'archived'], $this->historyTypes());

        $audit = $this->pdo->prepare(
            "SELECT action, after_data::text AS after_data FROM audit_logs
             WHERE entity_type = 'customer' AND entity_id = :code ORDER BY id",
        );
        $audit->execute(['code' => $this->customerCode]);
        $entries = $audit->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(3, $entries);
        self::assertSame('customer.created', $entries[0]['action']);
        /** @var array<string, mixed> $after */
        $after = json_decode((string) $entries[0]['after_data'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $after['display_name']);
        self::assertSame('[REDACTED]', $after['email']);
        self::assertSame('[REDACTED]', $after['phone']);
        self::assertSame('[REDACTED]', $after['tax_identifier']);
    }

    /** @return array<string, mixed> */
    private function input(string $displayName): array
    {
        return [
            'customer_code' => $this->customerCode,
            'status' => 'active',
            'customer_type' => 'person',
            'display_name' => $displayName,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'Mario@example.test',
            'phone' => '+39 333 1234567',
            'tax_identifier' => 'RSSMRA80A01H501U',
            'locale' => 'it-IT',
        ];
    }

    /** @return array<string, mixed> */
    private function customer(): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM customers WHERE customer_code = :code');
        $statement->execute(['code' => $this->customerCode]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    private function customerId(): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM customers WHERE customer_code = :code');
        $statement->execute(['code' => $this->customerCode]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return list<string> */
    private function historyTypes(): array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT history.change_type
FROM customer_history history
JOIN customers customer ON customer.id = history.customer_id
WHERE customer.customer_code = :code
ORDER BY history.version
SQL);
        $statement->execute(['code' => $this->customerCode]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }
}
