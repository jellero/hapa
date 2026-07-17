<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Modules\Procurement\Domain\SupplierPurchaseOrderStatus;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class BusinessDataOwnershipSchemaTest extends TestCase
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

    public function testSpaceOfferIsSeparatedFromTheCanonicalProduct(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $productId = $this->insertId(
            'INSERT INTO catalog_items (sku, currency) VALUES (:sku, :currency) RETURNING id',
            ['sku' => 'SKU-' . $suffix, 'currency' => 'EUR'],
        );
        $supplierId = (int) $this->value("SELECT id FROM suppliers WHERE code = 'space'");

        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO supplier_catalog_items (
    supplier_id, catalog_item_id, external_item_id, purchase_cost_minor,
    currency, available_quantity, source_version, observed_at
) VALUES (
    :supplier_id, :catalog_item_id, :external_item_id, 1299,
    'EUR', 12, :source_version, NOW()
)
SQL);
        $statement->execute([
            'supplier_id' => $supplierId,
            'catalog_item_id' => $productId,
            'external_item_id' => 'SPACE-' . $suffix,
            'source_version' => 'version-' . $suffix,
        ]);

        self::assertSame(1, (int) $this->value(
            'SELECT COUNT(*) FROM supplier_catalog_items WHERE catalog_item_id = ' . $productId,
        ));
    }

    public function testSaleAndSpacePurchaseHaveIndependentStates(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(5)));
        $marketplaceId = $this->insertId(
            "INSERT INTO marketplaces (code, name, adapter_key, active, business_status, created_at, updated_at)
             VALUES (:code, 'Test', 'test', FALSE, 'pilot', NOW(), NOW()) RETURNING id",
            ['code' => 'test-' . strtolower($suffix)],
        );
        $orderId = $this->insertId(
            "INSERT INTO orders (
                marketplace_id, order_number, external_order_id, status, currency, version, created_at, updated_at
             ) VALUES (
                :marketplace_id, :order_number, :external_order_id, 'imported', 'EUR', 1, NOW(), NOW()
             ) RETURNING id",
            [
                'marketplace_id' => $marketplaceId,
                'order_number' => 'ORD-' . $suffix,
                'external_order_id' => 'EXT-' . $suffix,
            ],
        );
        $supplierId = (int) $this->value("SELECT id FROM suppliers WHERE code = 'space'");
        $purchaseId = $this->insertId(
            "INSERT INTO supplier_purchase_orders (
                order_id, supplier_id, purchase_number, status, currency
             ) VALUES (
                :order_id, :supplier_id, :purchase_number, 'requested', 'EUR'
             ) RETURNING id",
            [
                'order_id' => $orderId,
                'supplier_id' => $supplierId,
                'purchase_number' => 'PO-' . $suffix,
            ],
        );

        self::assertSame('imported', $this->value('SELECT status FROM orders WHERE id = ' . $orderId));
        self::assertSame('requested', $this->value('SELECT status FROM supplier_purchase_orders WHERE id = ' . $purchaseId));
    }

    public function testPurchaseStatusConstraintMatchesTheProcurementDomain(): void
    {
        $statement = $this->pdo->query(<<<'SQL'
SELECT pg_get_constraintdef(oid)
FROM pg_constraint
WHERE conname = 'supplier_purchase_orders_status_check'
SQL);
        self::assertNotFalse($statement);
        $definition = $statement->fetchColumn();
        self::assertIsString($definition);

        /** @var array<int, list<string>> $matches */
        $matches = [];
        preg_match_all("/'([^']+)'/", $definition, $matches);
        $databaseStatuses = array_values(array_unique($matches[1]));
        $domainStatuses = array_map(
            static fn (SupplierPurchaseOrderStatus $status): string => $status->value,
            SupplierPurchaseOrderStatus::cases(),
        );
        sort($databaseStatuses);
        sort($domainStatuses);

        self::assertSame($domainStatuses, $databaseStatuses);
    }

    public function testCustomerHistoryIsAppendOnlyByVersion(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(5)));
        $customerId = $this->insertId(
            "INSERT INTO customers (customer_code, display_name)
             VALUES (:code, 'Cliente test') RETURNING id",
            ['code' => 'CUST-' . $suffix],
        );
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO customer_history (customer_id, version, change_type, snapshot, occurred_at)
VALUES (:customer_id, :version, 'profile.created', CAST(:snapshot AS JSONB), NOW())
SQL);
        $statement->execute([
            'customer_id' => $customerId,
            'version' => 1,
            'snapshot' => '{"display_name":"Cliente test"}',
        ]);

        self::assertSame(1, (int) $this->value(
            'SELECT COUNT(*) FROM customer_history WHERE customer_id = ' . $customerId,
        ));
    }

    /** @param array<string, int|string|null> $parameters */
    private function insertId(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function value(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
