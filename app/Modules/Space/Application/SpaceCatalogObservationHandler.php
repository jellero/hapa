<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Application;

use DateTimeImmutable;
use Hapa\Core\Database\TransactionManager;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Catalog\Contract\CatalogOfferRecalculator;
use Hapa\Modules\Space\Contract\SpaceCatalogObservation;
use Hapa\Modules\Space\Domain\SpaceCatalogIngestionOutcome;
use JsonException;
use PDO;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class SpaceCatalogObservationHandler
{
    private const DATABASE_TIMESTAMP = 'Y-m-d H:i:s.uP';

    public function __construct(
        private PDO $pdo,
        private TransactionManager $transactions,
        private CatalogOfferRecalculator $offerRecalculator,
    ) {
    }

    public function handle(MessageEnvelope $message): SpaceCatalogIngestionResult
    {
        $observation = SpaceCatalogObservation::fromEnvelope($message);

        return $this->transactions->transactional(
            fn (): SpaceCatalogIngestionResult => $this->ingest($observation),
        );
    }

    /** @throws JsonException */
    private function ingest(SpaceCatalogObservation $observation): SpaceCatalogIngestionResult
    {
        $supplierId = $this->spaceSupplierId();
        $this->lockIdentity($supplierId, $observation->externalItemId);

        $observationId = $this->reserveObservation($supplierId, $observation);
        if ($observationId === null) {
            return $this->duplicateResult($supplierId, $observation);
        }

        $offer = $this->offerByExternalIdentity($supplierId, $observation->externalItemId);
        if ($offer !== null) {
            return $this->ingestExistingOffer($observationId, $offer, $observation);
        }

        return $this->ingestNewOffer($observationId, $supplierId, $observation);
    }

    /** @param array<string,mixed> $offer */
    private function ingestExistingOffer(int $observationId, array $offer, SpaceCatalogObservation $observation): SpaceCatalogIngestionResult
    {
        $catalogItemId = (int) $offer['catalog_item_id'];
        $lastObservedAt = $offer['observed_at'] === null ? null : new DateTimeImmutable((string) $offer['observed_at']);
        if ($lastObservedAt !== null && $lastObservedAt > $observation->observedAt) {
            return $this->finishObservation($observationId, $catalogItemId, 'ignored', SpaceCatalogIngestionOutcome::IgnoredStale, 'Osservazione precedente a quella già applicata.');
        }
        $this->updateOffer((int) $offer['id'], $observation);
        $this->updatePendingProduct($catalogItemId, $observation);
        $this->offerRecalculator->recalculateProduct($this->pdo, $catalogItemId);
        return $this->finishObservation($observationId, $catalogItemId, 'applied', SpaceCatalogIngestionOutcome::Updated);
    }

    private function ingestNewOffer(int $observationId, int $supplierId, SpaceCatalogObservation $observation): SpaceCatalogIngestionResult
    {
        $eanMatches = $this->catalogItemsByEan($observation->ean);
        $skuMatch = $this->catalogItemBySku($observation->supplierSku);
        if (count($eanMatches) > 1) {
            return $this->identityConflict(
                $observationId,
                'L’EAN Space corrisponde a più prodotti HAPA.',
            );
        }
        if ($eanMatches !== [] && $skuMatch !== null && $eanMatches[0] !== $skuMatch) {
            return $this->identityConflict(
                $observationId,
                'EAN e SKU Space identificano prodotti HAPA differenti.',
            );
        }

        $catalogItemId = $eanMatches[0] ?? $skuMatch;
        $outcome = SpaceCatalogIngestionOutcome::LinkedExisting;
        if ($catalogItemId === null) {
            $catalogItemId = $this->createPendingProduct($observation);
            $outcome = SpaceCatalogIngestionOutcome::CreatedPendingReview;
        } else {
            $this->updatePendingProduct($catalogItemId, $observation);
        }

        $this->createOffer($supplierId, $catalogItemId, $observation);
        $this->offerRecalculator->recalculateProduct($this->pdo, $catalogItemId);

        return $this->finishObservation(
            $observationId,
            $catalogItemId,
            'applied',
            $outcome,
        );
    }

    private function spaceSupplierId(): int
    {
        $statement = $this->pdo->query("SELECT id FROM suppliers WHERE code = 'space' AND active FOR SHARE");
        if ($statement === false) {
            throw new HapaRuntimeException('Impossibile leggere il fornitore Space.');
        }
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new HapaRuntimeException('Fornitore Space non configurato o disabilitato.');
        }

        return (int) $id;
    }

    private function lockIdentity(int $supplierId, string $externalItemId): void
    {
        $statement = $this->pdo->prepare(
            'SELECT pg_advisory_xact_lock(hashtextextended(:identity, 0))',
        );
        $statement->execute(['identity' => $supplierId . ':' . $externalItemId]);
    }

    /** @throws JsonException */
    private function reserveObservation(int $supplierId, SpaceCatalogObservation $observation): ?int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO supplier_catalog_observations (
    message_id, supplier_id, external_item_id, source_version,
    status, payload, observed_at, created_at
) VALUES (
    :message_id, :supplier_id, :external_item_id, :source_version,
    'processing', CAST(:payload AS JSONB), :observed_at, NOW()
)
ON CONFLICT DO NOTHING
RETURNING id
SQL);
        $statement->execute([
            'message_id' => $observation->messageId,
            'supplier_id' => $supplierId,
            'external_item_id' => $observation->externalItemId,
            'source_version' => $observation->sourceVersion,
            'payload' => json_encode($observation->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'observed_at' => $observation->observedAt->format(self::DATABASE_TIMESTAMP),
        ]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function duplicateResult(
        int $supplierId,
        SpaceCatalogObservation $observation,
    ): SpaceCatalogIngestionResult {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, catalog_item_id, outcome
FROM supplier_catalog_observations
WHERE message_id = :message_id
   OR (supplier_id = :supplier_id AND external_item_id = :external_item_id AND source_version = :source_version)
ORDER BY id
LIMIT 1
SQL);
        $statement->execute([
            'message_id' => $observation->messageId,
            'supplier_id' => $supplierId,
            'external_item_id' => $observation->externalItemId,
            'source_version' => $observation->sourceVersion,
        ]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            throw new HapaRuntimeException('Osservazione duplicata non recuperabile.');
        }

        return new SpaceCatalogIngestionResult(
            (int) $row['id'],
            $row['catalog_item_id'] === null ? null : (int) $row['catalog_item_id'],
            SpaceCatalogIngestionOutcome::Duplicate,
            is_string($row['outcome']) ? 'Esito originale: ' . $row['outcome'] : null,
        );
    }

    /** @return array{id: int|string, catalog_item_id: int|string, observed_at: string|null}|null */
    private function offerByExternalIdentity(int $supplierId, string $externalItemId): ?array
    {
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT id, catalog_item_id, observed_at
FROM supplier_catalog_items
WHERE supplier_id = :supplier_id AND external_item_id = :external_item_id
FOR UPDATE
SQL);
        $statement->execute([
            'supplier_id' => $supplierId,
            'external_item_id' => $externalItemId,
        ]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => is_int($row['id']) || is_string($row['id']) ? $row['id'] : 0,
            'catalog_item_id' => is_int($row['catalog_item_id']) || is_string($row['catalog_item_id'])
                ? $row['catalog_item_id']
                : 0,
            'observed_at' => is_string($row['observed_at']) ? $row['observed_at'] : null,
        ];
    }

    /** @return list<int> */
    private function catalogItemsByEan(?string $ean): array
    {
        if ($ean === null) {
            return [];
        }

        $statement = $this->pdo->prepare('SELECT id FROM catalog_items WHERE ean = :ean ORDER BY id FOR UPDATE');
        $statement->execute(['ean' => $ean]);

        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $statement->fetchAll(PDO::FETCH_COLUMN),
        ));
    }

    private function catalogItemBySku(string $sku): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM catalog_items WHERE sku = :sku FOR UPDATE');
        $statement->execute(['sku' => $sku]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function createPendingProduct(SpaceCatalogObservation $observation): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO catalog_items (
    sku, ean, name, description, currency, active, onboarding_status,
    safety_stock, version, created_at, updated_at
) VALUES (
    :sku, :ean, :name, :description, :currency, FALSE, 'pending_review',
    0, 1, NOW(), NOW()
)
RETURNING id
SQL);
        $statement->execute([
            'sku' => $observation->supplierSku,
            'ean' => $observation->ean,
            'name' => $observation->name,
            'description' => $observation->description,
            'currency' => $observation->currency,
        ]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new HapaRuntimeException('Creazione prodotto HAPA fallita.');
        }

        return (int) $id;
    }

    private function updatePendingProduct(int $catalogItemId, SpaceCatalogObservation $observation): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE catalog_items
SET ean = COALESCE(ean, :ean),
    name = COALESCE(:name, name),
    description = COALESCE(:description, description),
    version = version + 1,
    updated_at = NOW()
WHERE id = :id AND onboarding_status = 'pending_review'
SQL);
        $statement->execute([
            'ean' => $observation->ean,
            'name' => $observation->name,
            'description' => $observation->description,
            'id' => $catalogItemId,
        ]);
    }

    private function createOffer(
        int $supplierId,
        int $catalogItemId,
        SpaceCatalogObservation $observation,
    ): void {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO supplier_catalog_items (
    supplier_id, catalog_item_id, external_item_id, supplier_sku,
    purchase_cost_minor, currency, available_quantity, source_version,
    observed_at, active, created_at, updated_at
) VALUES (
    :supplier_id, :catalog_item_id, :external_item_id, :supplier_sku,
    :purchase_cost_minor, :currency, :available_quantity, :source_version,
    :observed_at, TRUE, NOW(), NOW()
)
ON CONFLICT (supplier_id, catalog_item_id) DO UPDATE
SET external_item_id = EXCLUDED.external_item_id,
    supplier_sku = EXCLUDED.supplier_sku,
    purchase_cost_minor = EXCLUDED.purchase_cost_minor,
    currency = EXCLUDED.currency,
    available_quantity = EXCLUDED.available_quantity,
    source_version = EXCLUDED.source_version,
    observed_at = EXCLUDED.observed_at,
    active = TRUE,
    updated_at = NOW()
SQL);
        $statement->execute($this->offerParameters($supplierId, $catalogItemId, $observation));
    }

    private function updateOffer(int $offerId, SpaceCatalogObservation $observation): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE supplier_catalog_items
SET supplier_sku = :supplier_sku,
    purchase_cost_minor = :purchase_cost_minor,
    currency = :currency,
    available_quantity = :available_quantity,
    source_version = :source_version,
    observed_at = :observed_at,
    active = TRUE,
    updated_at = NOW()
WHERE id = :id
SQL);
        $statement->execute([
            'supplier_sku' => $observation->supplierSku,
            'purchase_cost_minor' => $observation->purchaseCostMinor,
            'currency' => $observation->currency,
            'available_quantity' => $observation->availableQuantity,
            'source_version' => $observation->sourceVersion,
            'observed_at' => $observation->observedAt->format(self::DATABASE_TIMESTAMP),
            'id' => $offerId,
        ]);
    }

    /** @return array<string, int|string|null> */
    private function offerParameters(
        int $supplierId,
        int $catalogItemId,
        SpaceCatalogObservation $observation,
    ): array {
        return [
            'supplier_id' => $supplierId,
            'catalog_item_id' => $catalogItemId,
            'external_item_id' => $observation->externalItemId,
            'supplier_sku' => $observation->supplierSku,
            'purchase_cost_minor' => $observation->purchaseCostMinor,
            'currency' => $observation->currency,
            'available_quantity' => $observation->availableQuantity,
            'source_version' => $observation->sourceVersion,
            'observed_at' => $observation->observedAt->format(self::DATABASE_TIMESTAMP),
        ];
    }

    private function identityConflict(int $observationId, string $reason): SpaceCatalogIngestionResult
    {
        return $this->finishObservation(
            $observationId,
            null,
            'manual_review',
            SpaceCatalogIngestionOutcome::IdentityConflict,
            $reason,
        );
    }

    private function finishObservation(
        int $observationId,
        ?int $catalogItemId,
        string $status,
        SpaceCatalogIngestionOutcome $outcome,
        ?string $reason = null,
    ): SpaceCatalogIngestionResult {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE supplier_catalog_observations
SET catalog_item_id = :catalog_item_id,
    status = :status,
    outcome = :outcome,
    reason = :reason,
    processed_at = NOW()
WHERE id = :id AND status = 'processing'
SQL);
        $statement->execute([
            'catalog_item_id' => $catalogItemId,
            'status' => $status,
            'outcome' => $outcome->value,
            'reason' => $reason,
            'id' => $observationId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new HapaRuntimeException('Finalizzazione osservazione catalogo Space fallita.');
        }

        return new SpaceCatalogIngestionResult($observationId, $catalogItemId, $outcome, $reason);
    }
}
