<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Cache\ReadModelCache;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\CatalogPublicationRuleManagement;
use InvalidArgumentException;
use PDO;
use PDOException;

final readonly class CatalogPublicationRuleService implements CatalogPublicationRuleManagement
{
    private const FIELDS = ['sku', 'ean', 'supplier_id', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity'];
    private const OPERATORS = ['equals', 'contains', 'starts_with', 'ends_with', 'minimum', 'maximum'];

    public function __construct(
        private ConnectionFactory $connections,
        private Clock $clock,
        private ?ReadModelCache $cache = null,
    )
    {
    }

    public function all(?int $commercialCatalogId = null): array
    {
        $statement = $this->connections->create()->prepare(<<<'SQL'
SELECT rule.id, rule.code, rule.name, rule.action, rule.field, rule.operator,
       rule.match_value, rule.priority, rule.enabled, marketplace.code AS marketplace_code
FROM catalog_publication_rules AS rule
LEFT JOIN marketplaces AS marketplace ON marketplace.id = rule.marketplace_id
WHERE rule.retired_at IS NULL
  AND (CAST(:catalog_id AS BIGINT) IS NULL OR rule.commercial_catalog_id = :catalog_id)
ORDER BY rule.priority, rule.id
SQL);
        $statement->execute(['catalog_id' => $commercialCatalogId]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(array $input, UserIdentity $actor): void
    {
        $action = (string) ($input['action'] ?? '');
        $field = (string) ($input['field'] ?? '');
        $operator = (string) ($input['operator'] ?? '');
        $value = trim((string) ($input['match_value'] ?? ''));
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        if ($code === '') {
            $code = sprintf(
                '%s-%s-%s',
                $action,
                str_replace('_', '-', $field),
                substr(hash('sha256', $operator . '|' . $value . '|' . bin2hex(random_bytes(8))), 0, 10),
            );
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $name = sprintf(
                '%s %s %s %s',
                $action === 'include' ? 'Includi' : 'Escludi',
                str_replace('_', ' ', $field),
                str_replace('_', ' ', $operator),
                $value,
            );
        }
        $priority = filter_var($input['priority'] ?? 100, FILTER_VALIDATE_INT);
        $catalogId = filter_var($input['commercial_catalog_id'] ?? null, FILTER_VALIDATE_INT);
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,99}$/D', $code) !== 1 || $name === '' || mb_strlen($name) > 180
            || !in_array($action, ['include', 'exclude'], true) || !in_array($field, self::FIELDS, true)
            || !in_array($operator, self::OPERATORS, true) || $value === '' || mb_strlen($value) > 500
            || !is_int($priority) || $priority < 0 || !is_int($catalogId) || $catalogId < 1) {
            throw new InvalidArgumentException('Regola di pubblicazione non valida.');
        }
        if (in_array($field, ['delivery_time_days', 'available_quantity'], true)
            && (!ctype_digit($value) || !in_array($operator, ['minimum', 'maximum', 'equals'], true))) {
            throw new InvalidArgumentException('La regola numerica richiede un valore intero e un operatore compatibile.');
        }
        $marketplace = trim((string) ($input['marketplace_id'] ?? ''));
        $marketplaceId = $marketplace === '' ? null : filter_var($marketplace, FILTER_VALIDATE_INT);
        if ($marketplaceId !== null && (!is_int($marketplaceId) || $marketplaceId < 1)) {
            throw new InvalidArgumentException('Marketplace non valido.');
        }
        $pdo = $this->connections->create();
        $catalog = $pdo->prepare(<<<'SQL'
SELECT EXISTS (
    SELECT 1
    FROM commercial_catalogs catalog
    WHERE catalog.id = :catalog_id AND catalog.retired_at IS NULL
      AND (
        CAST(:marketplace_id AS BIGINT) IS NULL
        OR EXISTS (
            SELECT 1 FROM commercial_catalog_marketplaces link
            WHERE link.commercial_catalog_id = catalog.id
              AND link.marketplace_id = CAST(:assigned_marketplace_id AS BIGINT)
        )
      )
)
SQL);
        $catalog->execute([
            'catalog_id' => $catalogId,
            'marketplace_id' => $marketplaceId,
            'assigned_marketplace_id' => $marketplaceId,
        ]);
        if (!filter_var($catalog->fetchColumn(), FILTER_VALIDATE_BOOL)) {
            throw new InvalidArgumentException('Il marketplace deve appartenere al catalogo selezionato.');
        }
        $statement = $pdo->prepare(<<<'SQL'
INSERT INTO catalog_publication_rules (
    commercial_catalog_id, code, name, marketplace_id, action, field, operator, match_value,
    priority, enabled, version, created_by, created_at, updated_at
) VALUES (
    :commercial_catalog_id, :code, :name, :marketplace_id, :action, :field, :operator, :match_value,
    :priority, TRUE, 1, :created_by, :created_at, :updated_at
)
SQL);
        $now = $this->clock->now()->format(DATE_ATOM);
        try {
            $statement->execute([
                'commercial_catalog_id' => $catalogId, 'code' => $code, 'name' => $name,
                'marketplace_id' => $marketplaceId,
                'action' => $action, 'field' => $field, 'operator' => $operator, 'match_value' => $value,
                'priority' => $priority, 'created_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                throw new InvalidArgumentException('Il codice del filtro è già utilizzato.', previous: $exception);
            }
            throw $exception;
        }
        $this->cache?->forget($this->eligibleCountCacheKey($catalogId));
    }

    public function retire(int $id, UserIdentity $actor): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Regola di pubblicazione non valida.');
        }
        $statement = $this->connections->create()->prepare(<<<'SQL'
UPDATE catalog_publication_rules
SET enabled = FALSE, retired_at = :now, updated_at = :now, version = version + 1
WHERE id = :id AND retired_at IS NULL
RETURNING commercial_catalog_id
SQL);
        $statement->execute(['id' => $id, 'now' => $this->clock->now()->format(DATE_ATOM)]);
        $catalogId = $statement->fetchColumn();
        if ($catalogId !== false) {
            $this->cache?->forget($this->eligibleCountCacheKey((int) $catalogId));
        }
    }

    private function eligibleCountCacheKey(int $catalogId): string
    {
        return sprintf('hapa:read:v1:catalog-eligible:%d', $catalogId);
    }
}
