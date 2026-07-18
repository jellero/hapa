<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\CatalogProductManagement;
use Hapa\Core\Ui\CatalogReviewConflict;
use InvalidArgumentException;
use JsonException;
use PDO;
use Throwable;

final class CatalogProductReviewService implements CatalogProductManagement
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly Clock $clock,
        private readonly MarketplaceOfferRecalculator $offerRecalculator,
    ) {
    }

    public function review(
        int $id,
        int $expectedVersion,
        string $decision,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        if ($id < 1 || $expectedVersion < 1 || !in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Decisione di revisione prodotto non valida.');
        }
        $pdo = $this->connection();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $before = $this->snapshot($id, true);
            if ($before['onboarding_status'] !== 'pending_review') {
                throw new InvalidArgumentException('Il prodotto è già stato revisionato.');
            }
            $statement = $pdo->prepare(<<<'SQL'
UPDATE catalog_items
SET onboarding_status = :decision,
    active = CAST(:active AS BOOLEAN),
    version = version + 1,
    updated_at = :updated_at
WHERE id = :id AND version = :expected_version AND onboarding_status = 'pending_review'
RETURNING version
SQL);
            $statement->execute([
                'decision' => $decision,
                'active' => $decision === 'approved' ? 'true' : 'false',
                'updated_at' => $now,
                'id' => $id,
                'expected_version' => $expectedVersion,
            ]);
            $version = $statement->fetchColumn();
            if ($version === false) {
                throw new CatalogReviewConflict('Il prodotto è stato modificato da un altro operatore.');
            }
            $after = $this->snapshot($id);
            $this->historyAndAudit($id, (int) $version, $decision, $before, $after, $actor, $correlationId, $now);
            $this->offerRecalculator->recalculateProduct($pdo, $id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function recalculateOffers(): int
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $updated = $this->offerRecalculator->recalculateAll($pdo);
            $pdo->commit();

            return $updated;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateSafetyStock(
        int $id,
        int $expectedVersion,
        int $safetyStock,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        if ($id < 1 || $expectedVersion < 1 || $safetyStock < 0) {
            throw new InvalidArgumentException('Scorta di sicurezza non valida.');
        }
        $pdo = $this->connection();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $before = $this->snapshot($id, true);
            $statement = $pdo->prepare(<<<'SQL'
UPDATE catalog_items
SET safety_stock = :safety_stock,
    version = version + 1,
    updated_at = :updated_at
WHERE id = :id AND version = :expected_version
RETURNING version
SQL);
            $statement->execute([
                'safety_stock' => $safetyStock,
                'updated_at' => $now,
                'id' => $id,
                'expected_version' => $expectedVersion,
            ]);
            $version = $statement->fetchColumn();
            if ($version === false) {
                throw new CatalogReviewConflict('Il prodotto è stato modificato da un altro operatore.');
            }
            $after = $this->snapshot($id);
            $this->historyAndAudit(
                $id,
                (int) $version,
                'safety_stock_updated',
                $before,
                $after,
                $actor,
                $correlationId,
                $now,
            );
            $this->offerRecalculator->recalculateProduct($pdo, $id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(int $id, bool $forUpdate = false): array
    {
        $lock = $forUpdate ? 'FOR UPDATE' : '';
        $statement = $this->connection()->prepare(<<<SQL
SELECT id, sku, ean, name, description, currency, active, onboarding_status,
       safety_stock, version, created_at, updated_at
FROM catalog_items WHERE id = :id
{$lock}
SQL);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new InvalidArgumentException('Prodotto non trovato.');
        }
        $row['id'] = (int) $row['id'];
        $row['version'] = (int) $row['version'];
        $row['safety_stock'] = (int) $row['safety_stock'];
        $row['active'] = filter_var($row['active'], FILTER_VALIDATE_BOOL);

        return $row;
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @throws JsonException
     */
    private function historyAndAudit(
        int $id,
        int $version,
        string $decision,
        array $before,
        array $after,
        UserIdentity $actor,
        string $correlationId,
        string $now,
    ): void {
        $beforeJson = json_encode($before, JSON_THROW_ON_ERROR);
        $afterJson = json_encode($after, JSON_THROW_ON_ERROR);
        $history = $this->connection()->prepare(<<<'SQL'
INSERT INTO catalog_item_history (catalog_item_id, version, action, snapshot, actor_id, correlation_id, created_at)
VALUES (:id, :version, :action, CAST(:snapshot AS JSONB), :actor_id, :correlation_id, :created_at)
SQL);
        $history->execute([
            'id' => $id,
            'version' => $version,
            'action' => $decision,
            'snapshot' => $afterJson,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'created_at' => $now,
        ]);
        $audit = $this->connection()->prepare(<<<'SQL'
INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at)
VALUES (:actor_id, :action, 'catalog_item', :entity_id, CAST(:before_data AS JSONB), CAST(:after_data AS JSONB), :correlation_id, :created_at)
SQL);
        $audit->execute([
            'actor_id' => $actor->id,
            'action' => 'catalog.item_' . $decision,
            'entity_id' => (string) $id,
            'before_data' => $beforeJson,
            'after_data' => $afterJson,
            'correlation_id' => $correlationId,
            'created_at' => $now,
        ]);
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
