<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\CatalogPublicationRuleManagement;
use InvalidArgumentException;
use PDO;

final readonly class CatalogPublicationRuleService implements CatalogPublicationRuleManagement
{
    private const FIELDS = ['sku', 'branch_suffix', 'artist', 'title', 'format', 'label', 'category', 'family', 'group', 'delivery_time_days', 'available_quantity'];
    private const OPERATORS = ['equals', 'contains', 'starts_with', 'ends_with', 'minimum', 'maximum'];

    public function __construct(private ConnectionFactory $connections, private Clock $clock)
    {
    }

    public function all(): array
    {
        $statement = $this->connections->create()->query(<<<'SQL'
SELECT rule.id, rule.code, rule.name, rule.action, rule.field, rule.operator,
       rule.match_value, rule.priority, rule.enabled, marketplace.code AS marketplace_code
FROM catalog_publication_rules AS rule
LEFT JOIN marketplaces AS marketplace ON marketplace.id = rule.marketplace_id
WHERE rule.retired_at IS NULL
ORDER BY rule.priority, rule.id
SQL);

        return $statement === false ? [] : array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(array $input, UserIdentity $actor): void
    {
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $action = (string) ($input['action'] ?? '');
        $field = (string) ($input['field'] ?? '');
        $operator = (string) ($input['operator'] ?? '');
        $value = trim((string) ($input['match_value'] ?? ''));
        $priority = filter_var($input['priority'] ?? 100, FILTER_VALIDATE_INT);
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,99}$/D', $code) !== 1 || $name === '' || mb_strlen($name) > 180
            || !in_array($action, ['include', 'exclude'], true) || !in_array($field, self::FIELDS, true)
            || !in_array($operator, self::OPERATORS, true) || $value === '' || mb_strlen($value) > 500
            || !is_int($priority) || $priority < 0) {
            throw new InvalidArgumentException('Regola di pubblicazione non valida.');
        }
        if (in_array($field, ['delivery_time_days', 'available_quantity'], true)
            && (!ctype_digit($value) || !in_array($operator, ['minimum', 'maximum', 'equals'], true))) {
            throw new InvalidArgumentException('La regola numerica richiede un valore intero e un operatore compatibile.');
        }
        $marketplace = trim((string) ($input['marketplace_id'] ?? ''));
        $statement = $this->connections->create()->prepare(<<<'SQL'
INSERT INTO catalog_publication_rules (
    code, name, marketplace_id, action, field, operator, match_value,
    priority, enabled, version, created_by, created_at, updated_at
) VALUES (
    :code, :name, :marketplace_id, :action, :field, :operator, :match_value,
    :priority, TRUE, 1, :created_by, :created_at, :updated_at
)
SQL);
        $now = $this->clock->now()->format(DATE_ATOM);
        $statement->execute([
            'code' => $code, 'name' => $name, 'marketplace_id' => $marketplace === '' ? null : (int) $marketplace,
            'action' => $action, 'field' => $field, 'operator' => $operator, 'match_value' => $value,
            'priority' => $priority, 'created_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function retire(int $id, UserIdentity $actor): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Regola di pubblicazione non valida.');
        }
        $statement = $this->connections->create()->prepare(
            'UPDATE catalog_publication_rules SET enabled = FALSE, retired_at = :now, updated_at = :now, version = version + 1 WHERE id = :id AND retired_at IS NULL',
        );
        $statement->execute(['id' => $id, 'now' => $this->clock->now()->format(DATE_ATOM)]);
    }
}
