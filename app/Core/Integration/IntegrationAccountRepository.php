<?php

declare(strict_types=1);

namespace Hapa\Core\Integration;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class IntegrationAccountRepository
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
SELECT account.*,
       COALESCE((SELECT jsonb_agg(capability ORDER BY capability)
                 FROM integration_account_capabilities capability
                 WHERE capability.integration_account_id = account.id), '[]'::jsonb) AS capabilities,
       COALESCE((SELECT jsonb_object_agg(setting.setting_key, setting.setting_value ORDER BY setting.setting_key)
                 FROM integration_account_settings setting
                 WHERE setting.integration_account_id = account.id), '{}'::jsonb) AS settings
FROM integration_accounts account
ORDER BY account.provider_code, account.display_name
SQL);
        if ($statement === false) {
            throw new RuntimeException('Impossibile leggere gli account integrazione.');
        }
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['configuration_version'] = (int) $row['configuration_version'];
            $row['capabilities'] = json_decode((string) $row['capabilities'], true, 512, JSON_THROW_ON_ERROR);
            $row['settings'] = json_decode((string) $row['settings'], true, 512, JSON_THROW_ON_ERROR);

            return $row;
        }, $rows));
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        foreach ($this->all() as $account) {
            if ($account['id'] === $id) {
                return $account;
            }
        }

        throw new RuntimeException('Account integrazione non trovato.');
    }

    /** @param array<string, mixed> $status @throws JsonException */
    public function recordSecretStatus(int $id, array $status, UserIdentity $actor, string $correlationId): void
    {
        $secretStatus = $status['status'] ?? null;
        $secretVersion = $status['secret_version'] ?? null;
        if (!is_string($secretStatus) || !in_array($secretStatus, ['configured', 'revoked'], true)
            || !is_int($secretVersion) || $secretVersion < 1) {
            throw new RuntimeException('Stato credenziali Automation non valido.');
        }
        $rotatedAt = isset($status['rotated_at']) && is_string($status['rotated_at']) ? $status['rotated_at'] : null;
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(<<<'SQL'
UPDATE integration_accounts
SET secret_status = :secret_status, secret_version = :secret_version,
    secret_rotated_at = :secret_rotated_at, connection_test_status = 'never',
    connection_tested_at = NULL, last_error = NULL, updated_at = :updated_at
WHERE id = :id AND secret_version <= :secret_version
SQL);
            $statement->execute([
                'id' => $id,
                'secret_status' => $secretStatus,
                'secret_version' => $secretVersion,
                'secret_rotated_at' => $rotatedAt,
                'updated_at' => $now,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Account integrazione non disponibile o stato credenziali obsoleto.');
            }
            $auditPayload = json_encode([
                'secret_status' => $secretStatus,
                'secret_version' => $secretVersion,
                'secret_rotated_at' => $rotatedAt,
            ], JSON_THROW_ON_ERROR);
            $audit = $pdo->prepare(<<<'SQL'
INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, after_data, correlation_id, created_at)
VALUES (:actor_id, :action, 'integration_account', :entity_id, CAST(:after_data AS JSONB), :correlation_id, :created_at)
SQL);
            $audit->execute([
                'actor_id' => $actor->id,
                'action' => $secretStatus === 'revoked' ? 'integration.secrets_revoked' : 'integration.secrets_replaced',
                'entity_id' => (string) $id,
                'after_data' => $auditPayload,
                'correlation_id' => $correlationId,
                'created_at' => $now,
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $configuration @throws JsonException */
    public function create(array $configuration, UserIdentity $actor, string $correlationId): int
    {
        $pdo = $this->connection();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(<<<'SQL'
INSERT INTO integration_accounts (
    provider_code, code, display_name, environment, description, desired_status,
    configuration_version, secret_status, connection_test_status, created_at, updated_at
) VALUES (
    :provider, :code, :display_name, :environment, :description, 'disabled',
    1, 'missing', 'never', :created_at, :updated_at
) RETURNING id
SQL);
            $statement->execute([
                'provider' => $configuration['provider'],
                'code' => $configuration['code'],
                'display_name' => $configuration['display_name'],
                'environment' => $configuration['environment'],
                'description' => $configuration['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $statement->fetchColumn();
            $this->replaceChildren($id, $configuration['capabilities'], $configuration['settings']);
            $snapshot = $this->snapshot($id);
            $this->historyAndAudit($id, 1, 'created', $snapshot, $actor, $correlationId, $now);
            $pdo->commit();

            return $id;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $configuration @throws JsonException */
    public function update(
        int $id,
        int $expectedVersion,
        array $configuration,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        $pdo = $this->connection();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(<<<'SQL'
UPDATE integration_accounts
SET provider_code = :provider, code = :code, display_name = :display_name,
    environment = :environment, description = :description,
    configuration_version = configuration_version + 1,
    connection_test_status = 'never', connection_tested_at = NULL,
    last_error = NULL, updated_at = :updated_at
WHERE id = :id AND configuration_version = :expected_version AND desired_status <> 'retired'
RETURNING configuration_version
SQL);
            $statement->execute([
                'id' => $id,
                'expected_version' => $expectedVersion,
                'provider' => $configuration['provider'],
                'code' => $configuration['code'],
                'display_name' => $configuration['display_name'],
                'environment' => $configuration['environment'],
                'description' => $configuration['description'],
                'updated_at' => $now,
            ]);
            $version = $statement->fetchColumn();
            if ($version === false) {
                throw new RuntimeException('Configurazione modificata da un altro operatore o account non disponibile.');
            }
            $this->replaceChildren($id, $configuration['capabilities'], $configuration['settings']);
            $snapshot = $this->snapshot($id);
            $this->historyAndAudit($id, (int) $version, 'updated', $snapshot, $actor, $correlationId, $now);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @throws JsonException */
    public function retire(
        int $id,
        int $expectedVersion,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        $pdo = $this->connection();
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(<<<'SQL'
UPDATE integration_accounts
SET desired_status = 'retired', configuration_version = configuration_version + 1,
    updated_at = :updated_at
WHERE id = :id AND configuration_version = :expected_version AND desired_status <> 'retired'
RETURNING configuration_version
SQL);
            $statement->execute([
                'id' => $id,
                'expected_version' => $expectedVersion,
                'updated_at' => $now,
            ]);
            $version = $statement->fetchColumn();
            if ($version === false) {
                throw new RuntimeException('Configurazione modificata da un altro operatore o account già ritirato.');
            }
            $snapshot = $this->snapshot($id);
            $this->historyAndAudit($id, (int) $version, 'retired', $snapshot, $actor, $correlationId, $now);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param list<string> $capabilities
     * @param array<string, mixed> $settings
     * @throws JsonException
     */
    private function replaceChildren(int $id, array $capabilities, array $settings): void
    {
        $pdo = $this->connection();
        $pdo->prepare('DELETE FROM integration_account_capabilities WHERE integration_account_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM integration_account_settings WHERE integration_account_id = :id')->execute(['id' => $id]);
        $capabilityStatement = $pdo->prepare(<<<'SQL'
INSERT INTO integration_account_capabilities (integration_account_id, capability, enabled)
VALUES (:id, :capability, TRUE)
SQL);
        foreach ($capabilities as $capability) {
            $capabilityStatement->execute(['id' => $id, 'capability' => $capability]);
        }
        $settingStatement = $pdo->prepare(<<<'SQL'
INSERT INTO integration_account_settings (integration_account_id, setting_key, setting_value)
VALUES (:id, :setting_key, CAST(:setting_value AS JSONB))
SQL);
        foreach ($settings as $key => $value) {
            $settingStatement->execute([
                'id' => $id,
                'setting_key' => $key,
                'setting_value' => json_encode($value, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /** @return array<string, mixed> @throws JsonException */
    private function snapshot(int $id): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT account.provider_code, account.code, account.display_name, account.environment,
       account.description, account.desired_status, account.configuration_version,
       COALESCE((SELECT jsonb_agg(capability ORDER BY capability)
                 FROM integration_account_capabilities capability
                 WHERE capability.integration_account_id = account.id), '[]'::jsonb) AS capabilities,
       COALESCE((SELECT jsonb_object_agg(setting.setting_key, setting.setting_value ORDER BY setting.setting_key)
                 FROM integration_account_settings setting
                 WHERE setting.integration_account_id = account.id), '{}'::jsonb) AS settings
FROM integration_accounts account WHERE account.id = :id
SQL);
        $statement->execute(['id' => $id]);
        $snapshot = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($snapshot)) {
            throw new RuntimeException('Account integrazione non trovato.');
        }
        $snapshot['configuration_version'] = (int) $snapshot['configuration_version'];
        $snapshot['capabilities'] = json_decode((string) $snapshot['capabilities'], true, 512, JSON_THROW_ON_ERROR);
        $snapshot['settings'] = json_decode((string) $snapshot['settings'], true, 512, JSON_THROW_ON_ERROR);

        return $snapshot;
    }

    /** @param array<string, mixed> $snapshot @throws JsonException */
    private function historyAndAudit(
        int $id,
        int $version,
        string $action,
        array $snapshot,
        UserIdentity $actor,
        string $correlationId,
        string $now,
    ): void {
        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $history = $this->connection()->prepare(<<<'SQL'
INSERT INTO integration_account_history (
    integration_account_id, configuration_version, action, snapshot, actor_id, correlation_id, created_at
) VALUES (:id, :version, :action, CAST(:snapshot AS JSONB), :actor_id, :correlation_id, :created_at)
SQL);
        $history->execute([
            'id' => $id,
            'version' => $version,
            'action' => $action,
            'snapshot' => $encoded,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'created_at' => $now,
        ]);
        $audit = $this->connection()->prepare(<<<'SQL'
INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, after_data, correlation_id, created_at)
VALUES (:actor_id, :action, 'integration_account', :entity_id, CAST(:after_data AS JSONB), :correlation_id, :created_at)
SQL);
        $audit->execute([
            'actor_id' => $actor->id,
            'action' => 'integration.account_' . $action,
            'entity_id' => (string) $id,
            'after_data' => $encoded,
            'correlation_id' => $correlationId,
            'created_at' => $now,
        ]);
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
