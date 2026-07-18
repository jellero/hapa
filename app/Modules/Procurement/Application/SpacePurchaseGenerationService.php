<?php

declare(strict_types=1);

namespace Hapa\Modules\Procurement\Application;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Core\Ui\SpacePurchaseManagement;
use InvalidArgumentException;
use PDO;
use Throwable;

final class SpacePurchaseGenerationService implements SpacePurchaseManagement
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly ProviderCommandFactory $commands,
        ?PDO $connection = null,
    ) {
        $this->connection = $connection;
    }

    public function generateForOrder(string $orderNumber, string $correlationId): void
    {
        $orderNumber = strtoupper(trim($orderNumber));
        if ($orderNumber === '') {
            throw new InvalidArgumentException('Numero ordine non valido.');
        }

        $runtime = $this->runtime();
        $runtime['transactions']->transactional(function () use ($runtime, $orderNumber, $correlationId): void {
            $statement = $runtime['pdo']->prepare(<<<'SQL'
SELECT id
FROM orders
WHERE order_number = :order_number AND marketplace_account_id IS NOT NULL
FOR UPDATE
SQL);
            $statement->execute(['order_number' => $orderNumber]);
            $id = $statement->fetchColumn();
            if ($id === false) {
                throw new InvalidArgumentException('Ordine marketplace HAPA non trovato.');
            }

            $runtime['generator']->generate((int) $id, $correlationId);
        });
    }

    public function generateOutstanding(string $correlationId, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        if (!$this->hasEligibleSpaceAccount()) {
            return ['examined' => 0, 'generated' => 0, 'manual_review' => 0, 'failed' => 0];
        }

        $runtime = $this->runtime();
        $statement = $runtime['pdo']->prepare(<<<'SQL'
SELECT customer_order.id
FROM orders customer_order
LEFT JOIN supplier_purchase_orders purchase
  ON purchase.order_id = customer_order.id
 AND purchase.auto_generated
 AND purchase.supplier_id = (SELECT id FROM suppliers WHERE code = 'space' AND active)
WHERE customer_order.marketplace_account_id IS NOT NULL
  AND customer_order.status <> 'cancelled'
  AND (purchase.id IS NULL OR purchase.status = 'manual_review')
ORDER BY customer_order.id
LIMIT :limit
SQL);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $orderIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $generated = 0;
        $manualReview = 0;
        $failed = 0;

        foreach ($orderIds as $index => $orderId) {
            try {
                $runtime['transactions']->transactional(function () use ($runtime, $orderId, $correlationId, $index): void {
                    $runtime['generator']->generate($orderId, $correlationId . ':backfill:' . ($index + 1));
                });
            } catch (Throwable) {
                ++$failed;
                continue;
            }
            $status = $this->purchaseStatus($orderId);
            if ($status === 'manual_review') {
                ++$manualReview;
            } elseif ($status !== null) {
                ++$generated;
            }
        }

        return [
            'examined' => count($orderIds),
            'generated' => $generated,
            'manual_review' => $manualReview,
            'failed' => $failed,
        ];
    }

    private function hasEligibleSpaceAccount(): bool
    {
        $statement = $this->connection()->query(<<<'SQL'
SELECT EXISTS (
    SELECT 1
    FROM integration_accounts account
    JOIN integration_account_capabilities capability
      ON capability.integration_account_id = account.id
     AND capability.capability = 'purchase_orders.write'
     AND capability.enabled
    WHERE account.provider_code = 'space'
      AND account.desired_status IN ('pilot', 'active')
      AND account.secret_status = 'configured'
      AND account.connection_test_status = 'passed'
      AND account.automation_configuration_version = account.configuration_version
)
SQL);

        if ($statement === false) {
            return false;
        }

        return filter_var($statement->fetchColumn(), FILTER_VALIDATE_BOOL);
    }

    private function purchaseStatus(int $orderId): ?string
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT purchase.status
FROM supplier_purchase_orders purchase
JOIN suppliers supplier ON supplier.id = purchase.supplier_id AND supplier.code = 'space'
WHERE purchase.order_id = :order_id AND purchase.auto_generated
ORDER BY purchase.id DESC
LIMIT 1
SQL);
        $statement->execute(['order_id' => $orderId]);
        $status = $statement->fetchColumn();

        return is_string($status) ? $status : null;
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }

    /** @return array{pdo:PDO,transactions:PdoTransactionManager,generator:AutomaticSpacePurchaseGenerator} */
    private function runtime(): array
    {
        $pdo = $this->connection();

        return [
            'pdo' => $pdo,
            'transactions' => new PdoTransactionManager($pdo),
            'generator' => new AutomaticSpacePurchaseGenerator(
                $pdo,
                new PostgresOutboxRepository($pdo),
                $this->commands,
            ),
        ];
    }
}
