<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use DateTimeImmutable;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\PricingRuleManagement;
use Hapa\Core\Ui\PricingRuleConflict;
use Hapa\Modules\Catalog\Domain\PriceAdjustmentType;
use Hapa\Modules\Catalog\Domain\PricingRule;
use Hapa\Modules\Catalog\Domain\PricingRuleScope;
use InvalidArgumentException;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class PricingRuleService implements PricingRuleManagement
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly Clock $clock,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $statement = $this->connection()->query(<<<'SQL'
SELECT rule.*, marketplace.code AS marketplace_code, marketplace.name AS marketplace_name
FROM pricing_rules rule
LEFT JOIN marketplaces marketplace ON marketplace.id = rule.marketplace_id
ORDER BY rule.retired_at NULLS FIRST, rule.enabled DESC, rule.priority DESC, rule.code
SQL);
        if ($statement === false) {
            throw new RuntimeException('Impossibile leggere le regole di ricarico.');
        }

        return array_values(array_map(self::hydrate(...), $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /** @return list<array{id: int, code: string, name: string}> */
    public function marketplaces(): array
    {
        $statement = $this->connection()->query('SELECT id, code, name FROM marketplaces WHERE business_status <> \'retired\' ORDER BY name');
        if ($statement === false) {
            throw new RuntimeException('Impossibile leggere i marketplace.');
        }

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /** @param array<string, mixed> $input */
    public function create(array $input, UserIdentity $actor, string $correlationId): int
    {
        $pdo = $this->connection();
        $rule = $this->validate($input);
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $duplicate = $pdo->prepare('SELECT 1 FROM pricing_rules WHERE code = :code');
            $duplicate->execute(['code' => $rule['code']]);
            if ($duplicate->fetchColumn() !== false) {
                throw new InvalidArgumentException('Esiste già una regola con questo codice.');
            }
            $statement = $pdo->prepare(<<<'SQL'
INSERT INTO pricing_rules (
    code, name, scope, marketplace_id, sku, adjustment_type, adjustment_value,
    currency, minimum_price_minor, maximum_price_minor, priority, enabled,
    valid_from, valid_until, version, created_at, updated_at
) VALUES (
    :code, :name, :scope, :marketplace_id, :sku, :adjustment_type, :adjustment_value,
    :currency, :minimum_price_minor, :maximum_price_minor, :priority, CAST(:enabled AS BOOLEAN),
    :valid_from, :valid_until, 1, :created_at, :updated_at
) RETURNING id
SQL);
            $statement->execute([...$rule, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $statement->fetchColumn();
            $snapshot = $this->snapshot($id);
            $this->historyAndAudit($id, 1, 'created', null, $snapshot, $actor, $correlationId, $now);
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(
        int $id,
        int $expectedVersion,
        array $input,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        if ($id < 1 || $expectedVersion < 1) {
            throw new InvalidArgumentException('Regola o versione non valida.');
        }
        $pdo = $this->connection();
        $rule = $this->validate($input);
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $before = $this->snapshot($id, true);
            if ($before['retired_at'] !== null) {
                throw new InvalidArgumentException('Una regola ritirata non può essere modificata.');
            }
            $statement = $pdo->prepare(<<<'SQL'
UPDATE pricing_rules
SET code = :code, name = :name, scope = :scope, marketplace_id = :marketplace_id,
    sku = :sku, adjustment_type = :adjustment_type, adjustment_value = :adjustment_value,
    currency = :currency, minimum_price_minor = :minimum_price_minor,
    maximum_price_minor = :maximum_price_minor, priority = :priority,
    enabled = CAST(:enabled AS BOOLEAN), valid_from = :valid_from, valid_until = :valid_until,
    version = version + 1, updated_at = :updated_at
WHERE id = :id AND version = :expected_version AND retired_at IS NULL
RETURNING version
SQL);
            $statement->execute([
                ...$rule,
                'id' => $id,
                'expected_version' => $expectedVersion,
                'updated_at' => $now,
            ]);
            $version = $statement->fetchColumn();
            if ($version === false) {
                throw new PricingRuleConflict('La regola è stata modificata da un altro operatore.');
            }
            $after = $this->snapshot($id);
            $this->historyAndAudit($id, (int) $version, 'updated', $before, $after, $actor, $correlationId, $now);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function retire(int $id, int $expectedVersion, UserIdentity $actor, string $correlationId): void
    {
        if ($id < 1 || $expectedVersion < 1) {
            throw new InvalidArgumentException('Regola o versione non valida.');
        }
        $pdo = $this->connection();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $before = $this->snapshot($id, true);
            $statement = $pdo->prepare(<<<'SQL'
UPDATE pricing_rules
SET enabled = FALSE, retired_at = :retired_at, version = version + 1, updated_at = :updated_at
WHERE id = :id AND version = :expected_version AND retired_at IS NULL
RETURNING version
SQL);
            $statement->execute([
                'id' => $id,
                'expected_version' => $expectedVersion,
                'retired_at' => $now,
                'updated_at' => $now,
            ]);
            $version = $statement->fetchColumn();
            if ($version === false) {
                throw new PricingRuleConflict('La regola è stata modificata o ritirata da un altro operatore.');
            }
            $after = $this->snapshot($id);
            $this->historyAndAudit($id, (int) $version, 'retired', $before, $after, $actor, $correlationId, $now);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, int|string|null>
     */
    private function validate(array $input): array
    {
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Nome della regola non valido.');
        }
        $scope = PricingRuleScope::tryFrom((string) ($input['scope'] ?? ''))
            ?? throw new InvalidArgumentException('Ambito della regola non valido.');
        $adjustmentType = PriceAdjustmentType::tryFrom((string) ($input['adjustment_type'] ?? ''))
            ?? throw new InvalidArgumentException('Tipo di ricarico non valido.');
        $marketplaceId = self::nullablePositiveInt($input['marketplace_id'] ?? null);
        $marketplaceCode = $marketplaceId === null ? null : $this->marketplaceCode($marketplaceId);
        $sku = self::nullableString($input['sku'] ?? null);
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'EUR')));
        $adjustmentValue = self::integer($input['adjustment_value'] ?? null, 'Valore di ricarico');
        $priority = self::integer($input['priority'] ?? 100, 'Priorità');
        $minimum = self::nullableInteger($input['minimum_price_minor'] ?? null, 'Prezzo minimo');
        $maximum = self::nullableInteger($input['maximum_price_minor'] ?? null, 'Prezzo massimo');
        new PricingRule(
            $code,
            $scope,
            $marketplaceCode,
            $sku,
            $adjustmentType,
            $adjustmentValue,
            $currency,
            $priority,
            $minimum,
            $maximum,
        );
        $validFrom = self::date($input['valid_from'] ?? null);
        $validUntil = self::date($input['valid_until'] ?? null);
        if ($validFrom !== null && $validUntil !== null && $validFrom >= $validUntil) {
            throw new InvalidArgumentException('La fine validità deve essere successiva all’inizio.');
        }

        return [
            'code' => $code,
            'name' => $name,
            'scope' => $scope->value,
            'marketplace_id' => $marketplaceId,
            'sku' => $sku,
            'adjustment_type' => $adjustmentType->value,
            'adjustment_value' => $adjustmentValue,
            'currency' => $currency,
            'minimum_price_minor' => $minimum,
            'maximum_price_minor' => $maximum,
            'priority' => $priority,
            'enabled' => ($input['enabled'] ?? false) ? 'true' : 'false',
            'valid_from' => $validFrom?->format(DATE_ATOM),
            'valid_until' => $validUntil?->format(DATE_ATOM),
        ];
    }

    private function marketplaceCode(int $id): string
    {
        $statement = $this->connection()->prepare('SELECT code FROM marketplaces WHERE id = :id AND business_status <> \'retired\'');
        $statement->execute(['id' => $id]);
        $code = $statement->fetchColumn();
        if (!is_string($code)) {
            throw new InvalidArgumentException('Marketplace non disponibile.');
        }

        return $code;
    }

    /** @return array<string, mixed> */
    private function snapshot(int $id, bool $forUpdate = false): array
    {
        $lockClause = $this->lockClause($forUpdate);
        $statement = $this->connection()->prepare(<<<SQL
SELECT rule.*, marketplace.code AS marketplace_code, marketplace.name AS marketplace_name
FROM pricing_rules rule
LEFT JOIN marketplaces marketplace ON marketplace.id = rule.marketplace_id
WHERE rule.id = :id
{$lockClause}
SQL);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new InvalidArgumentException('Regola di ricarico non trovata.');
        }

        return self::hydrate($row);
    }

    private function lockClause(bool $forUpdate): string
    {
        return $forUpdate ? 'FOR UPDATE OF rule' : '';
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     * @throws JsonException
     */
    private function historyAndAudit(
        int $id,
        int $version,
        string $action,
        ?array $before,
        array $after,
        UserIdentity $actor,
        string $correlationId,
        string $now,
    ): void {
        $afterJson = json_encode($after, JSON_THROW_ON_ERROR);
        $history = $this->connection()->prepare(<<<'SQL'
INSERT INTO pricing_rule_history (pricing_rule_id, version, action, snapshot, actor_id, correlation_id, created_at)
VALUES (:id, :version, :action, CAST(:snapshot AS JSONB), :actor_id, :correlation_id, :created_at)
SQL);
        $history->execute([
            'id' => $id,
            'version' => $version,
            'action' => $action,
            'snapshot' => $afterJson,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'created_at' => $now,
        ]);
        $audit = $this->connection()->prepare(<<<'SQL'
INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at)
VALUES (:actor_id, :action, 'pricing_rule', :entity_id, CAST(:before_data AS JSONB), CAST(:after_data AS JSONB), :correlation_id, :created_at)
SQL);
        $audit->execute([
            'actor_id' => $actor->id,
            'action' => 'pricing.rule_' . $action,
            'entity_id' => (string) $id,
            'before_data' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_data' => $afterJson,
            'correlation_id' => $correlationId,
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrate(array $row): array
    {
        foreach (['id', 'marketplace_id', 'adjustment_value', 'minimum_price_minor', 'maximum_price_minor', 'priority', 'version'] as $key) {
            $row[$key] = $row[$key] === null ? null : (int) $row[$key];
        }
        $row['enabled'] = filter_var($row['enabled'], FILTER_VALIDATE_BOOL);

        return $row;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function integer(mixed $value, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException($label . ' deve essere un intero.');
        }

        return (int) $value;
    }

    private static function nullableInteger(mixed $value, string $label): ?int
    {
        return self::nullableString($value) === null ? null : self::integer($value, $label);
    }

    private static function nullablePositiveInt(mixed $value): ?int
    {
        if (self::nullableString($value) === null) {
            return null;
        }
        $integer = self::integer($value, 'Marketplace');
        if ($integer < 1) {
            throw new InvalidArgumentException('Marketplace non valido.');
        }

        return $integer;
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        $value = self::nullableString($value);
        if ($value === null) {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Data di validità non valida.', 0, $exception);
        }
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
