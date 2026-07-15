<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Database\ConnectionFactory;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Throwable;

final class DatabaseConstraintsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory())->create();
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

    public function testOrderLineRejectsQuantitiesAboveOrderedQuantity(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $marketplaceId = $this->insertAndReturnId(
            'INSERT INTO marketplaces (code, name, adapter_key, active, created_at, updated_at)
             VALUES (:code, :name, :adapter, TRUE, NOW(), NOW()) RETURNING id',
            [
                'code' => 'test-' . $suffix,
                'name' => 'Test Marketplace',
                'adapter' => 'test',
            ],
        );

        $orderId = $this->insertAndReturnId(
            'INSERT INTO orders (
                marketplace_id, external_order_id, status, currency, version, created_at, updated_at
             ) VALUES (
                :marketplace_id, :external_order_id, :status, :currency, 1, NOW(), NOW()
             ) RETURNING id',
            [
                'marketplace_id' => $marketplaceId,
                'external_order_id' => 'order-' . $suffix,
                'status' => 'imported',
                'currency' => 'EUR',
            ],
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO order_lines (
                order_id, sku, quantity_ordered, quantity_available,
                quantity_to_ship, quantity_to_cancel, created_at, updated_at
             ) VALUES (
                :order_id, :sku, 2, 2, 2, 1, NOW(), NOW()
             )',
        );

        $this->expectException(PDOException::class);
        $statement->execute([
            'order_id' => $orderId,
            'sku' => 'SKU-' . $suffix,
        ]);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function insertAndReturnId(string $sql, array $parameters): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }
}
