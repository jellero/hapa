<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Application;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Logging\SensitiveDataRedactor;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\CustomerConflict;
use Hapa\Core\Ui\CustomerManagement;
use Hapa\Modules\Customers\Domain\CustomerCode;
use Hapa\Modules\Customers\Domain\CustomerProfile;
use Hapa\Modules\Customers\Domain\CustomerStatus;
use Hapa\Modules\Customers\Domain\CustomerType;
use Hapa\Modules\Customers\Domain\EmailAddress;
use InvalidArgumentException;
use JsonException;
use PDO;
use Throwable;

final class CustomerService implements CustomerManagement
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly Clock $clock,
        private readonly SensitiveDataRedactor $redactor,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function create(array $input, UserIdentity $actor, string $correlationId): string
    {
        $profile = $this->profile($input, true);
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $duplicate = $pdo->prepare('SELECT 1 FROM customers WHERE customer_code = :customer_code');
            $duplicate->execute(['customer_code' => (string) $profile->code]);
            if ($duplicate->fetchColumn() !== false) {
                throw new InvalidArgumentException('Esiste già un cliente con questo codice.');
            }
            $statement = $pdo->prepare(<<<'SQL'
INSERT INTO customers (
    customer_code, status, customer_type, display_name, first_name, last_name,
    company_name, email, email_normalized, phone, tax_identifier, vat_number,
    locale, version, created_at, updated_at
) VALUES (
    :customer_code, :status, :customer_type, :display_name, :first_name, :last_name,
    :company_name, :email, :email_normalized, :phone, :tax_identifier, :vat_number,
    :locale, 1, :created_at, :updated_at
) RETURNING id
SQL);
            $statement->execute([...$this->parameters($profile), 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $statement->fetchColumn();
            $after = $this->snapshot($id);
            $this->historyAndAudit($id, 1, 'created', null, $after, $actor, $correlationId, $now);
            $pdo->commit();

            return (string) $profile->code;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(
        string $customerCode,
        int $expectedVersion,
        array $input,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        $code = new CustomerCode($customerCode);
        if ($expectedVersion < 1) {
            throw new InvalidArgumentException('Versione cliente non valida.');
        }
        $input['customer_code'] = (string) $code;
        $profile = $this->profile($input, false);
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $before = $this->snapshotByCode((string) $code, true);
            if ($profile->status === CustomerStatus::Archived && $before['status'] !== CustomerStatus::Archived->value) {
                throw new InvalidArgumentException('Usa l’azione dedicata per archiviare il cliente.');
            }
            $statement = $pdo->prepare(<<<'SQL'
UPDATE customers
SET status = :status, customer_type = :customer_type, display_name = :display_name,
    first_name = :first_name, last_name = :last_name, company_name = :company_name,
    email = :email, email_normalized = :email_normalized, phone = :phone,
    tax_identifier = :tax_identifier, vat_number = :vat_number, locale = :locale,
    version = version + 1, updated_at = :updated_at
WHERE customer_code = :customer_code AND version = :expected_version
RETURNING id, version
SQL);
            $statement->execute([
                ...$this->parameters($profile),
                'expected_version' => $expectedVersion,
                'updated_at' => $now,
            ]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($result)) {
                throw new CustomerConflict('Il cliente è stato modificato da un altro operatore. Ricarica la scheda.');
            }
            $id = (int) $result['id'];
            $version = (int) $result['version'];
            $after = $this->snapshot($id);
            $this->historyAndAudit($id, $version, 'updated', $before, $after, $actor, $correlationId, $now);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function archive(
        string $customerCode,
        int $expectedVersion,
        UserIdentity $actor,
        string $correlationId,
    ): void {
        $code = new CustomerCode($customerCode);
        if ($expectedVersion < 1) {
            throw new InvalidArgumentException('Versione cliente non valida.');
        }
        $now = $this->clock->now()->format(DATE_ATOM);
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $before = $this->snapshotByCode((string) $code, true);
            if ($before['status'] === CustomerStatus::Archived->value) {
                throw new InvalidArgumentException('Il cliente è già archiviato.');
            }
            $statement = $pdo->prepare(<<<'SQL'
UPDATE customers
SET status = :status, version = version + 1, updated_at = :updated_at
WHERE customer_code = :customer_code AND version = :expected_version
RETURNING id, version
SQL);
            $statement->execute([
                'status' => CustomerStatus::Archived->value,
                'updated_at' => $now,
                'customer_code' => (string) $code,
                'expected_version' => $expectedVersion,
            ]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($result)) {
                throw new CustomerConflict('Il cliente è stato modificato da un altro operatore. Ricarica la scheda.');
            }
            $id = (int) $result['id'];
            $version = (int) $result['version'];
            $after = $this->snapshot($id);
            $this->historyAndAudit($id, $version, 'archived', $before, $after, $actor, $correlationId, $now);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    private function profile(array $input, bool $generateCode): CustomerProfile
    {
        $rawCode = trim((string) ($input['customer_code'] ?? ''));
        $code = $rawCode === '' && $generateCode ? $this->generateCode() : new CustomerCode($rawCode);
        $status = CustomerStatus::tryFrom((string) ($input['status'] ?? CustomerStatus::Active->value))
            ?? throw new InvalidArgumentException('Stato cliente non valido.');
        $type = CustomerType::tryFrom((string) ($input['customer_type'] ?? ''))
            ?? throw new InvalidArgumentException('Tipo cliente non valido.');
        $emailValue = self::nullable($input['email'] ?? null);

        return new CustomerProfile(
            $code,
            $status,
            $type,
            (string) ($input['display_name'] ?? ''),
            self::nullable($input['first_name'] ?? null),
            self::nullable($input['last_name'] ?? null),
            self::nullable($input['company_name'] ?? null),
            $emailValue === null ? null : new EmailAddress($emailValue),
            self::nullable($input['phone'] ?? null),
            self::nullable($input['tax_identifier'] ?? null),
            self::nullable($input['vat_number'] ?? null),
            (string) ($input['locale'] ?? 'it-IT'),
        );
    }

    private function generateCode(): CustomerCode
    {
        return new CustomerCode('CUST-' . strtoupper(bin2hex(random_bytes(8))));
    }

    /** @return array<string, string|null> */
    private function parameters(CustomerProfile $profile): array
    {
        return [
            'customer_code' => (string) $profile->code,
            'status' => $profile->status->value,
            'customer_type' => $profile->type->value,
            'display_name' => $profile->displayName,
            'first_name' => $profile->firstName,
            'last_name' => $profile->lastName,
            'company_name' => $profile->companyName,
            'email' => $profile->email?->value,
            'email_normalized' => $profile->email?->normalized,
            'phone' => $profile->phone,
            'tax_identifier' => $profile->taxIdentifier,
            'vat_number' => $profile->vatNumber,
            'locale' => $profile->locale,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotByCode(string $customerCode, bool $forUpdate): array
    {
        $lock = $forUpdate ? 'FOR UPDATE' : '';
        $statement = $this->connection()->prepare(<<<SQL
SELECT * FROM customers WHERE customer_code = :customer_code {$lock}
SQL);
        $statement->execute(['customer_code' => $customerCode]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new InvalidArgumentException('Cliente non trovato.');
        }

        return self::hydrate($row);
    }

    /** @return array<string, mixed> */
    private function snapshot(int $id): array
    {
        $statement = $this->connection()->prepare('SELECT * FROM customers WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new InvalidArgumentException('Cliente non trovato.');
        }

        return self::hydrate($row);
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     * @throws JsonException
     */
    private function historyAndAudit(
        int $id,
        int $version,
        string $changeType,
        ?array $before,
        array $after,
        UserIdentity $actor,
        string $correlationId,
        string $now,
    ): void {
        $afterJson = json_encode($after, JSON_THROW_ON_ERROR);
        $history = $this->connection()->prepare(<<<'SQL'
INSERT INTO customer_history (
    customer_id, version, change_type, snapshot, actor_id, correlation_id, occurred_at
) VALUES (
    :customer_id, :version, :change_type, CAST(:snapshot AS JSONB), :actor_id, :correlation_id, :occurred_at
)
SQL);
        $history->execute([
            'customer_id' => $id,
            'version' => $version,
            'change_type' => $changeType,
            'snapshot' => $afterJson,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'occurred_at' => $now,
        ]);
        $audit = $this->connection()->prepare(<<<'SQL'
INSERT INTO audit_logs (
    actor_id, action, entity_type, entity_id, before_data, after_data, correlation_id, created_at
) VALUES (
    :actor_id, :action, 'customer', :entity_id, CAST(:before_data AS JSONB),
    CAST(:after_data AS JSONB), :correlation_id, :created_at
)
SQL);
        $audit->execute([
            'actor_id' => $actor->id,
            'action' => 'customer.' . $changeType,
            'entity_id' => (string) $after['customer_code'],
            'before_data' => $before === null ? null : json_encode($this->redactor->redact($before), JSON_THROW_ON_ERROR),
            'after_data' => json_encode($this->redactor->redact($after), JSON_THROW_ON_ERROR),
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
        return [
            'customer_code' => (string) $row['customer_code'],
            'status' => (string) $row['status'],
            'customer_type' => (string) $row['customer_type'],
            'display_name' => (string) $row['display_name'],
            'first_name' => self::nullable($row['first_name']),
            'last_name' => self::nullable($row['last_name']),
            'company_name' => self::nullable($row['company_name']),
            'email' => self::nullable($row['email']),
            'phone' => self::nullable($row['phone']),
            'tax_identifier' => self::nullable($row['tax_identifier']),
            'vat_number' => self::nullable($row['vat_number']),
            'locale' => (string) $row['locale'],
            'version' => (int) $row['version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
