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
        $this->ensureSpaceSupplier($observation);
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO supplier_catalog_items (
    supplier_id, catalog_item_id, external_item_id, supplier_sku,
    purchase_cost_minor, currency, available_quantity, source_version,
    observed_at, active, feed_name, artist, title, format, label, category,
    family, group_name, branch_suffix, delivery_time_days, source_status,
    precision_score, product_url, image_url, release_date, weight, weight_unit,
    missing_from_source, temu_sync_enabled, space_supplier_id, backorder_quantity,
    source_attributes, created_at, updated_at
) VALUES (
    :supplier_id, :catalog_item_id, :external_item_id, :supplier_sku,
    :purchase_cost_minor, :currency, :available_quantity, :source_version,
    :observed_at, TRUE, :feed_name, :artist, :title, :format, :label, :category,
    :family, :group_name, :branch_suffix, :delivery_time_days, :source_status,
    :precision_score, :product_url, :image_url, :release_date, :weight, :weight_unit,
    :missing_from_source, :temu_sync_enabled, :space_supplier_id, :backorder_quantity,
    CAST(:source_attributes AS JSONB), NOW(), NOW()
)
ON CONFLICT (supplier_id, catalog_item_id) DO UPDATE
SET external_item_id = EXCLUDED.external_item_id,
    supplier_sku = EXCLUDED.supplier_sku,
    purchase_cost_minor = EXCLUDED.purchase_cost_minor,
    currency = EXCLUDED.currency,
    available_quantity = EXCLUDED.available_quantity,
    source_version = EXCLUDED.source_version,
    observed_at = EXCLUDED.observed_at,
    feed_name = EXCLUDED.feed_name,
    artist = EXCLUDED.artist,
    title = EXCLUDED.title,
    format = EXCLUDED.format,
    label = EXCLUDED.label,
    category = EXCLUDED.category,
    family = EXCLUDED.family,
    group_name = EXCLUDED.group_name,
    branch_suffix = EXCLUDED.branch_suffix,
    delivery_time_days = EXCLUDED.delivery_time_days,
    source_status = EXCLUDED.source_status,
    precision_score = EXCLUDED.precision_score,
    product_url = EXCLUDED.product_url,
    image_url = EXCLUDED.image_url,
    release_date = EXCLUDED.release_date,
    weight = EXCLUDED.weight,
    weight_unit = EXCLUDED.weight_unit,
    missing_from_source = EXCLUDED.missing_from_source,
    temu_sync_enabled = EXCLUDED.temu_sync_enabled,
    space_supplier_id = EXCLUDED.space_supplier_id,
    backorder_quantity = EXCLUDED.backorder_quantity,
    source_attributes = EXCLUDED.source_attributes,
    active = TRUE,
    updated_at = NOW()
SQL);
        $statement->execute($this->offerParameters($supplierId, $catalogItemId, $observation));
    }

    private function updateOffer(int $offerId, SpaceCatalogObservation $observation): void
    {
        $this->ensureSpaceSupplier($observation);
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE supplier_catalog_items
SET supplier_sku = :supplier_sku,
    purchase_cost_minor = :purchase_cost_minor,
    currency = :currency,
    available_quantity = :available_quantity,
    source_version = :source_version,
    observed_at = :observed_at,
    feed_name = :feed_name,
    artist = :artist,
    title = :title,
    format = :format,
    label = :label,
    category = :category,
    family = :family,
    group_name = :group_name,
    branch_suffix = :branch_suffix,
    delivery_time_days = :delivery_time_days,
    source_status = :source_status,
    precision_score = :precision_score,
    product_url = :product_url,
    image_url = :image_url,
    release_date = :release_date,
    weight = :weight,
    weight_unit = :weight_unit,
    missing_from_source = :missing_from_source,
    temu_sync_enabled = :temu_sync_enabled,
    space_supplier_id = :space_supplier_id,
    backorder_quantity = :backorder_quantity,
    source_attributes = CAST(:source_attributes AS JSONB),
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
        ] + $this->feedParameters($observation));
    }

    /** @return array<string, int|float|string|null> */
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
        ] + $this->feedParameters($observation);
    }

    /** @return array<string, int|float|string|null> */
    private function feedParameters(SpaceCatalogObservation $observation): array
    {
        $a = $observation->attributes;
        $external = (string) ($a['idspace'] ?? $observation->externalItemId);
        $full = (string) ($a['idspacefull'] ?? $observation->supplierSku);
        $suffix = str_starts_with($full, $external) ? substr($full, strlen($external)) : null;
        $spaceSupplierId = $this->attributeString($a, 'id_fornitore', 64);
        if ($spaceSupplierId === null && is_string($suffix) && preg_match('/^[Aa]([0-9]+)$/D', $suffix, $match) === 1) {
            $spaceSupplierId = $match[1];
        }

        return [
            'feed_name' => $this->attributeString($a, 'feed_name', 80),
            'artist' => $this->firstAttributeString($a, ['discoteca_artista', 'artista'], 255),
            'title' => $this->firstAttributeString($a, ['discoteca_titolo', 'titolo'], 255),
            'format' => $this->firstAttributeString($a, ['discoteca_formato', 'formato', 'format'], 80),
            'label' => $this->firstAttributeString($a, ['discoteca_etichetta', 'etichetta', 'label'], 255),
            'category' => $this->attributeString($a, 'categoria', 160),
            'family' => $this->attributeString($a, 'famiglia', 160),
            'group_name' => $this->attributeString($a, 'gruppo', 160),
            'branch_suffix' => $suffix === '' ? null : substr((string) $suffix, 0, 40),
            'space_supplier_id' => $spaceSupplierId,
            'backorder_quantity' => max(0, $this->attributeInt($a, 'stock_qty_fornitore') ?? 0),
            'delivery_time_days' => $this->attributeInt($a, 'delitime'),
            'source_status' => $this->attributeInt($a, 'status'),
            'precision_score' => $this->attributeInt($a, 'precisione'),
            'product_url' => $this->firstAttributeString($a, ['url_pagina', 'url', 'product_url'], 2048),
            'image_url' => $this->firstAttributeString($a, ['url_immagine', 'url_img', 'image_url'], 2048),
            'release_date' => $this->attributeString($a, 'uscita', 20),
            'weight' => is_numeric($a['peso'] ?? null) ? (float) $a['peso'] : null,
            'weight_unit' => $this->attributeString($a, 'weight_unit', 16),
            'missing_from_source' => filter_var($a['missing_from_source'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0,
            'temu_sync_enabled' => filter_var($a['temu_sync_enabled'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0,
            'source_attributes' => json_encode($a === [] ? (object) [] : $a, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function ensureSpaceSupplier(SpaceCatalogObservation $observation): void
    {
        $supplierId = $this->feedParameters($observation)['space_supplier_id'];
        if (!is_string($supplierId) || $supplierId === '') {
            return;
        }
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO space_suppliers (space_supplier_id)
VALUES (:space_supplier_id)
ON CONFLICT (space_supplier_id) DO NOTHING
SQL);
        $statement->execute(['space_supplier_id' => $supplierId]);
    }

    /** @param array<string, mixed> $attributes */
    private function attributeString(array $attributes, string $key, int $maximum): ?string
    {
        $value = $attributes[$key] ?? null;
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $maximum);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string> $keys
     */
    private function firstAttributeString(array $attributes, array $keys, int $maximum): ?string
    {
        foreach ($keys as $key) {
            $value = $this->attributeString($attributes, $key, $maximum);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $attributes */
    private function attributeInt(array $attributes, string $key): ?int
    {
        $value = $attributes[$key] ?? null;
        return is_int($value) || is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1 ? (int) $value : null;
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
