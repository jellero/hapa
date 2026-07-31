<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Cache\ReadModelCache;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\CommercialCatalogManagement;
use Hapa\Modules\Catalog\Contract\CatalogOfferRecalculator;
use InvalidArgumentException;
use PDO;
use Throwable;

final readonly class CommercialCatalogService implements CommercialCatalogManagement
{
    public function __construct(
        private ConnectionFactory $connections,
        private Clock $clock,
        private CatalogOfferRecalculator $offerRecalculator,
        private ?ReadModelCache $cache = null,
    ) {
    }

    public function all(): array
    {
        $statement = $this->connections->create()->query(
            'SELECT * FROM (' . $this->catalogQuery() . ') catalog_summary ORDER BY priority, name',
        );

        return $statement === false ? [] : array_values(array_map($this->hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    public function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $statement = $this->connections->create()->prepare(
            'SELECT * FROM (' . $this->catalogQuery() . ') catalog_summary WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function preview(int $id, int $limit = 200): array
    {
        if ($id < 1) {
            return [];
        }
        $limit = max(1, min($limit, 500));
        $pdo = $this->connections->create();
        $rules = $this->configuredRules($pdo, $id);
        $marketplaceIds = $this->marketplaceIds($pdo, $id);
        if ($rules === [] || $marketplaceIds === []) {
            return [];
        }
        $products = $pdo->query(<<<'SQL'
SELECT item.id, item.sku, item.ean, item.name, item.onboarding_status, item.active,
       item.sellable_quantity, source.purchase_cost_minor, source.currency,
       source.space_supplier_id AS supplier_id,
       supplier_registry.code AS supplier_code, supplier_registry.legal_name AS supplier_name,
       source.artist, source.title, source.format,
       source.label, source.category, source.family, source.group_name AS "group",
       source.branch_suffix, source.delivery_time_days,
       COALESCE(source.available_quantity, 0) + COALESCE(source.backorder_quantity, 0) AS available_quantity
FROM catalog_items item
JOIN supplier_catalog_items source ON source.catalog_item_id = item.id AND source.active
JOIN suppliers supplier ON supplier.id = source.supplier_id AND supplier.code = 'space'
LEFT JOIN space_suppliers supplier_registry ON supplier_registry.space_supplier_id = source.space_supplier_id
WHERE item.onboarding_status <> 'rejected'
ORDER BY item.id
SQL);
        if ($products === false) {
            return [];
        }
        $preview = [];
        while (($product = $products->fetch(PDO::FETCH_ASSOC)) !== false) {
            $eligibleMarketplaceIds = $this->eligibleMarketplaceIds($product, $marketplaceIds, $rules);
            if ($eligibleMarketplaceIds === []) {
                continue;
            }
            $product['id'] = (int) $product['id'];
            $product['active'] = filter_var($product['active'], FILTER_VALIDATE_BOOL);
            $product['sellable_quantity'] = (int) $product['sellable_quantity'];
            $product['purchase_cost_minor'] = $product['purchase_cost_minor'] === null
                ? null
                : (int) $product['purchase_cost_minor'];
            $product['available_quantity'] = (int) $product['available_quantity'];
            $product['delivery_time_days'] = $product['delivery_time_days'] === null
                ? null
                : (int) $product['delivery_time_days'];
            $product['marketplace_ids'] = $eligibleMarketplaceIds;
            $preview[] = $product;
            if (count($preview) >= $limit) {
                break;
            }
        }

        return $preview;
    }

    public function setEnabled(
        int $id,
        bool $enabled,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        if ($id < 1) {
            throw new InvalidArgumentException('Catalogo commerciale non valido.');
        }
        if ($enabled) {
            $readiness = $this->activationReadiness($id);
            if (!$readiness['price'] || !$readiness['include'] || !$readiness['marketplace']) {
                throw new InvalidArgumentException(
                    'Prima di attivare il catalogo configura almeno un marketplace, un prezzo e un’inclusione.',
                );
            }
            if ($this->eligibleCount($id) < 1) {
                throw new InvalidArgumentException(
                    'L’anteprima non contiene prodotti: correggi inclusioni ed esclusioni prima di attivare.',
                );
            }
        }
        $pdo = $this->connections->create();
        $pdo->beginTransaction();
        try {
            $catalog = $pdo->prepare(<<<'SQL'
SELECT id, code, name, enabled, priority, version
FROM commercial_catalogs
WHERE id = :id AND retired_at IS NULL
FOR UPDATE
SQL);
            $catalog->execute(['id' => $id]);
            $before = $catalog->fetch(PDO::FETCH_ASSOC);
            if (!is_array($before)) {
                throw new InvalidArgumentException('Catalogo commerciale non trovato.');
            }
            if (filter_var($before['enabled'], FILTER_VALIDATE_BOOL) === $enabled) {
                $pdo->commit();
                return;
            }
            $updatedAt = $this->clock->now()->format(DATE_ATOM);
            $update = $pdo->prepare(
                'UPDATE commercial_catalogs '
                . 'SET enabled = :enabled, version = version + 1, updated_at = :updated_at WHERE id = :id',
            );
            $update->execute(['enabled' => $enabled ? 'true' : 'false', 'updated_at' => $updatedAt, 'id' => $id]);
            $after = $before;
            $after['enabled'] = $enabled;
            $after['version'] = (int) $before['version'] + 1;
            $audit = $pdo->prepare(<<<'SQL'
INSERT INTO audit_logs (
    actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at
) VALUES (
    :actor_id, :action, 'commercial_catalog', :entity_id,
    CAST(:before_data AS JSONB), CAST(:after_data AS JSONB), :correlation_id, :created_at
)
SQL);
            $audit->execute([
                'actor_id' => $actor->id,
                'action' => $enabled ? 'commercial_catalog.activated' : 'commercial_catalog.deactivated',
                'entity_id' => (string) $id,
                'before_data' => json_encode($before, JSON_THROW_ON_ERROR),
                'after_data' => json_encode($after, JSON_THROW_ON_ERROR),
                'correlation_id' => $correlationId === '' ? null : $correlationId,
                'created_at' => $updatedAt,
            ]);
            $this->offerRecalculator->recalculateCatalog($pdo, $id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function create(array $input, UserIdentity $actor): int
    {
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $priority = filter_var($input['priority'] ?? 100, FILTER_VALIDATE_INT);
        $marketplaceIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            is_array($input['marketplace_ids'] ?? null) ? $input['marketplace_ids'] : [],
        ), static fn (int $id): bool => $id > 0)));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,99}$/D', $code) !== 1 || $name === ''
            || mb_strlen($name) > 180 || mb_strlen($description) > 1000
            || !is_int($priority) || $priority < 0 || $priority > 100000 || $marketplaceIds === []) {
            throw new InvalidArgumentException('Il catalogo richiede codice, nome e almeno un marketplace.');
        }

        $pdo = $this->connections->create();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(<<<'SQL'
INSERT INTO commercial_catalogs (code, name, description, enabled, priority, version, created_by, created_at, updated_at)
VALUES (:code, :name, :description, FALSE, :priority, 1, :created_by, :created_at, :updated_at)
RETURNING id
SQL);
            $insert->execute([
                'code' => $code, 'name' => $name, 'description' => $description === '' ? null : $description,
                'priority' => $priority, 'created_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $insert->fetchColumn();
            $marketplace = $pdo->prepare(<<<'SQL'
INSERT INTO commercial_catalog_marketplaces (commercial_catalog_id, marketplace_id, created_at)
SELECT :catalog_id, id, :created_at FROM marketplaces
WHERE id = :marketplace_id AND business_status <> 'retired'
SQL);
            foreach ($marketplaceIds as $marketplaceId) {
                $marketplace->execute(['catalog_id' => $id, 'marketplace_id' => $marketplaceId, 'created_at' => $now]);
                if ($marketplace->rowCount() !== 1) {
                    throw new InvalidArgumentException('Marketplace non disponibile.');
                }
            }
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function delete(int $id, string $confirmation, UserIdentity $actor, string $correlationId): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Catalogo commerciale non valido.');
        }
        $pdo = $this->connections->create();
        $pdo->beginTransaction();
        try {
            $catalog = $pdo->prepare(<<<'SQL'
SELECT id, code, name, description, priority, version
FROM commercial_catalogs
WHERE id = :id
FOR UPDATE
SQL);
            $catalog->execute(['id' => $id]);
            $snapshot = $catalog->fetch(PDO::FETCH_ASSOC);
            if (!is_array($snapshot)) {
                throw new InvalidArgumentException('Catalogo commerciale non trovato.');
            }
            if (!hash_equals((string) $snapshot['name'], trim($confirmation))) {
                throw new InvalidArgumentException('Scrivi il nome esatto del catalogo per confermare la cancellazione.');
            }
            $disableCatalog = $pdo->prepare('UPDATE commercial_catalogs SET enabled = FALSE WHERE id = :id');
            $disableCatalog->execute(['id' => $id]);
            $this->offerRecalculator->recalculateCatalog($pdo, $id);
            $deleteHistory = $pdo->prepare(<<<'SQL'
DELETE FROM pricing_rule_history
WHERE pricing_rule_id IN (
    SELECT id FROM pricing_rules WHERE commercial_catalog_id = :catalog_id
)
SQL);
            $deleteHistory->execute(['catalog_id' => $id]);
            $deletePricing = $pdo->prepare('DELETE FROM pricing_rules WHERE commercial_catalog_id = :catalog_id');
            $deletePricing->execute(['catalog_id' => $id]);
            $deleteCatalog = $pdo->prepare('DELETE FROM commercial_catalogs WHERE id = :id');
            $deleteCatalog->execute(['id' => $id]);
            if ($deleteCatalog->rowCount() !== 1) {
                throw new InvalidArgumentException('Catalogo commerciale non trovato.');
            }
            $audit = $pdo->prepare(<<<'SQL'
INSERT INTO audit_logs (
    actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at
) VALUES (
    :actor_id, 'commercial_catalog.deleted', 'commercial_catalog', :entity_id,
    CAST(:before_data AS JSONB), NULL, :correlation_id, :created_at
)
SQL);
            $audit->execute([
                'actor_id' => $actor->id,
                'entity_id' => (string) $id,
                'before_data' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'correlation_id' => $correlationId === '' ? null : $correlationId,
                'created_at' => $this->clock->now()->format(DATE_ATOM),
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function catalogQuery(): string
    {
        return <<<'SQL'
SELECT catalog.*,
       COALESCE(STRING_AGG(DISTINCT marketplace.name, ', ' ORDER BY marketplace.name), '') AS marketplace_names,
       COUNT(DISTINCT price.id) FILTER (WHERE price.enabled AND price.retired_at IS NULL) AS pricing_rule_count,
       COUNT(DISTINCT include_rule.id) AS include_rule_count,
       COUNT(DISTINCT exclude_rule.id) AS exclude_rule_count,
       0 AS eligible_product_count
FROM commercial_catalogs catalog
LEFT JOIN commercial_catalog_marketplaces link ON link.commercial_catalog_id = catalog.id
LEFT JOIN marketplaces marketplace ON marketplace.id = link.marketplace_id
LEFT JOIN pricing_rules price ON price.commercial_catalog_id = catalog.id
LEFT JOIN catalog_publication_rules include_rule ON include_rule.commercial_catalog_id = catalog.id AND include_rule.action = 'include' AND include_rule.enabled AND include_rule.retired_at IS NULL
LEFT JOIN catalog_publication_rules exclude_rule ON exclude_rule.commercial_catalog_id = catalog.id AND exclude_rule.action = 'exclude' AND exclude_rule.enabled AND exclude_rule.retired_at IS NULL
GROUP BY catalog.id
SQL;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        foreach (['id', 'priority', 'version', 'pricing_rule_count', 'include_rule_count', 'exclude_rule_count', 'eligible_product_count'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        $row['enabled'] = filter_var($row['enabled'], FILTER_VALIDATE_BOOL);
        $row['configured'] = $row['pricing_rule_count'] > 0 && $row['include_rule_count'] > 0;
        $row['eligible_product_count'] = $row['configured'] ? $this->eligibleCount((int) $row['id']) : 0;
        $marketplaces = $this->connections->create()->prepare(
            'SELECT marketplace_id FROM commercial_catalog_marketplaces WHERE commercial_catalog_id = :id ORDER BY marketplace_id',
        );
        $marketplaces->execute(['id' => $row['id']]);
        $row['marketplace_ids'] = array_values(array_map('intval', $marketplaces->fetchAll(PDO::FETCH_COLUMN)));
        $row['ready'] = $row['configured']
            && $row['marketplace_ids'] !== []
            && $row['eligible_product_count'] > 0;
        $row['status'] = $row['enabled'] ? 'active' : ($row['ready'] ? 'ready' : 'draft');

        return $row;
    }

    private function eligibleCount(int $catalogId): int
    {
        $cached = $this->cache?->remember(
            sprintf('hapa:read:v1:catalog-eligible:%d', $catalogId),
            300,
            fn (): array => ['count' => $this->queryEligibleCount($catalogId)],
        );

        return is_array($cached) ? (int) ($cached['count'] ?? 0) : $this->queryEligibleCount($catalogId);
    }

    private function queryEligibleCount(int $catalogId): int
    {
        $pdo = $this->connections->create();
        $statement = $pdo->prepare(<<<'SQL'
WITH candidate_rules AS (
    SELECT
        item.id AS catalog_item_id,
        rule.field,
        link.marketplace_id,
        rule.priority,
        rule.action,
        rule.operator,
        lower(btrim(rule.match_value)) AS expected_value,
        lower(btrim(COALESCE(source.space_supplier_id::text, ''))) AS supplier_external_id,
        lower(btrim(COALESCE(space_supplier.code, ''))) AS supplier_code,
        lower(btrim(COALESCE(space_supplier.legal_name, ''))) AS supplier_name,
        lower(btrim(CASE rule.field
            WHEN 'sku' THEN item.sku
            WHEN 'ean' THEN item.ean
            WHEN 'supplier_id' THEN COALESCE(space_supplier.code, source.space_supplier_id::text, space_supplier.legal_name)
            WHEN 'branch_suffix' THEN source.branch_suffix
            WHEN 'artist' THEN source.artist
            WHEN 'title' THEN source.title
            WHEN 'format' THEN source.format
            WHEN 'label' THEN source.label
            WHEN 'category' THEN source.category
            WHEN 'family' THEN source.family
            WHEN 'group' THEN source.group_name
            WHEN 'delivery_time_days' THEN source.delivery_time_days::text
            WHEN 'available_quantity' THEN (
                COALESCE(source.available_quantity, 0) + COALESCE(source.backorder_quantity, 0)
            )::text
            ELSE NULL
        END)) AS actual_value,
        CASE rule.field
            WHEN 'delivery_time_days' THEN source.delivery_time_days
            WHEN 'available_quantity' THEN (
                COALESCE(source.available_quantity, 0) + COALESCE(source.backorder_quantity, 0)
            )
            ELSE NULL
        END AS actual_number
    FROM commercial_catalog_marketplaces link
    JOIN catalog_publication_rules rule
      ON rule.commercial_catalog_id = link.commercial_catalog_id
     AND rule.enabled
     AND rule.retired_at IS NULL
     AND (rule.marketplace_id IS NULL OR rule.marketplace_id = link.marketplace_id)
    JOIN catalog_items item ON item.onboarding_status <> 'rejected'
    JOIN supplier_catalog_items source ON source.catalog_item_id = item.id AND source.active
    JOIN suppliers supplier ON supplier.id = source.supplier_id AND supplier.code = 'space'
    LEFT JOIN space_suppliers space_supplier ON space_supplier.space_supplier_id = source.space_supplier_id
    WHERE link.commercial_catalog_id = :catalog_id
),
matching_rules AS (
    SELECT catalog_item_id, marketplace_id, priority, action
    FROM candidate_rules
    WHERE
        (
            field = 'supplier_id' AND (
                (operator = 'equals' AND expected_value IN (supplier_external_id, supplier_code, supplier_name))
                OR (operator = 'contains' AND position(expected_value IN concat_ws(' ', supplier_external_id, supplier_code, supplier_name)) > 0)
                OR (operator = 'starts_with' AND EXISTS (
                    SELECT 1 FROM unnest(ARRAY[supplier_external_id, supplier_code, supplier_name]) AS candidate(value)
                    WHERE left(candidate.value, length(expected_value)) = expected_value
                ))
                OR (operator = 'ends_with' AND EXISTS (
                    SELECT 1 FROM unnest(ARRAY[supplier_external_id, supplier_code, supplier_name]) AS candidate(value)
                    WHERE right(candidate.value, length(expected_value)) = expected_value
                ))
            )
        )
        OR (
            field <> 'supplier_id' AND (
                (operator = 'equals' AND actual_value = expected_value)
                OR (operator = 'contains' AND position(expected_value IN actual_value) > 0)
                OR (operator = 'starts_with' AND left(actual_value, length(expected_value)) = expected_value)
                OR (operator = 'ends_with' AND right(actual_value, length(expected_value)) = expected_value)
                OR (operator = 'minimum' AND actual_number >= expected_value::integer)
                OR (operator = 'maximum' AND actual_number <= expected_value::integer)
            )
        )
),
winning_priority AS (
    SELECT catalog_item_id, marketplace_id, min(priority) AS priority
    FROM matching_rules
    GROUP BY catalog_item_id, marketplace_id
),
eligible_products AS (
    SELECT matched.catalog_item_id, matched.marketplace_id
    FROM matching_rules matched
    JOIN winning_priority winning
      ON winning.catalog_item_id = matched.catalog_item_id
     AND winning.marketplace_id = matched.marketplace_id
     AND winning.priority = matched.priority
    GROUP BY matched.catalog_item_id, matched.marketplace_id
    HAVING bool_or(matched.action = 'include') AND NOT bool_or(matched.action = 'exclude')
)
SELECT count(DISTINCT catalog_item_id)
FROM eligible_products
SQL);
        $statement->execute(['catalog_id' => $catalogId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array{price: bool, include: bool, marketplace: bool} */
    private function activationReadiness(int $catalogId): array
    {
        $statement = $this->connections->create()->prepare(<<<'SQL'
SELECT
    EXISTS (
        SELECT 1 FROM pricing_rules
        WHERE commercial_catalog_id = :catalog_id AND enabled AND retired_at IS NULL
    ) AS has_price,
    EXISTS (
        SELECT 1 FROM catalog_publication_rules
        WHERE commercial_catalog_id = :catalog_id
          AND action = 'include' AND enabled AND retired_at IS NULL
    ) AS has_include,
    EXISTS (
        SELECT 1 FROM commercial_catalog_marketplaces
        WHERE commercial_catalog_id = :catalog_id
    ) AS has_marketplace
SQL);
        $statement->execute(['catalog_id' => $catalogId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'price' => filter_var($row['has_price'] ?? false, FILTER_VALIDATE_BOOL),
            'include' => filter_var($row['has_include'] ?? false, FILTER_VALIDATE_BOOL),
            'marketplace' => filter_var($row['has_marketplace'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function configuredRules(PDO $pdo, int $catalogId): array
    {
        $rules = $pdo->prepare(<<<'SQL'
SELECT action, field, operator, match_value, marketplace_id, priority
FROM catalog_publication_rules
WHERE commercial_catalog_id = :catalog_id AND enabled AND retired_at IS NULL
ORDER BY priority, CASE action WHEN 'exclude' THEN 0 ELSE 1 END, id
SQL);
        $rules->execute(['catalog_id' => $catalogId]);

        return array_values($rules->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<int> */
    private function marketplaceIds(PDO $pdo, int $catalogId): array
    {
        $marketplaces = $pdo->prepare(
            'SELECT marketplace_id FROM commercial_catalog_marketplaces WHERE commercial_catalog_id = :catalog_id',
        );
        $marketplaces->execute(['catalog_id' => $catalogId]);

        return array_values(array_map('intval', $marketplaces->fetchAll(PDO::FETCH_COLUMN)));
    }

    /**
     * @param array<string, mixed> $product
     * @param list<int> $marketplaceIds
     * @param list<array<string, mixed>> $rules
     * @return list<int>
     */
    private function eligibleMarketplaceIds(array $product, array $marketplaceIds, array $rules): array
    {
        $eligible = [];
        foreach ($marketplaceIds as $marketplaceId) {
            if (CatalogPublicationRuleMatcher::allows($product, $marketplaceId, $rules)) {
                $eligible[] = $marketplaceId;
            }
        }

        return $eligible;
    }
}
