<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

use DateTimeImmutable;
use Hapa\Core\Database\ConnectionFactory;
use PDO;

final class UserRepository
{
    private ?PDO $connection = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /** @return array{identity: UserIdentity, password_hash: string}|null */
    public function findActiveByEmail(string $email, DateTimeImmutable $now): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT id, email, display_name, role, password_hash
FROM app_users
WHERE lower(email) = lower(:email)
  AND status = 'active'
  AND (locked_until IS NULL OR locked_until <= :now)
LIMIT 1
SQL);
        $statement->execute(['email' => trim($email), 'now' => $now->format(DATE_ATOM)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'identity' => new UserIdentity(
                (string) $row['id'],
                (string) $row['email'],
                (string) $row['display_name'],
                (string) $row['role'],
            ),
            'password_hash' => (string) $row['password_hash'],
        ];
    }

    public function create(string $email, string $displayName, string $role, string $passwordHash, DateTimeImmutable $now): UserIdentity
    {
        $id = self::identifier();
        $statement = $this->connection()->prepare(<<<'SQL'
INSERT INTO app_users (
    id, email, display_name, role, password_hash, status, created_at, updated_at
) VALUES (
    :id, :email, :display_name, :role, :password_hash, 'active', :created_at, :updated_at
)
SQL);
        $statement->execute([
            'id' => $id,
            'email' => strtolower(trim($email)),
            'display_name' => trim($displayName),
            'role' => $role,
            'password_hash' => $passwordHash,
            'created_at' => $now->format(DATE_ATOM),
            'updated_at' => $now->format(DATE_ATOM),
        ]);

        return new UserIdentity($id, strtolower(trim($email)), trim($displayName), $role);
    }

    public function recordSuccessfulLogin(UserIdentity $user, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE app_users
SET failed_login_attempts = 0, locked_until = NULL, last_login_at = :now, updated_at = :now
WHERE id = :id
SQL);
        $statement->execute(['id' => $user->id, 'now' => $now->format(DATE_ATOM)]);
    }

    public function recordFailedLogin(string $email, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE app_users
SET failed_login_attempts = LEAST(failed_login_attempts + 1, 1000000),
    locked_until = CASE
        WHEN failed_login_attempts + 1 >= 10 THEN CAST(:now AS TIMESTAMPTZ) + INTERVAL '15 minutes'
        ELSE locked_until
    END,
    updated_at = :now
WHERE lower(email) = lower(:email)
SQL);
        $statement->execute(['email' => trim($email), 'now' => $now->format(DATE_ATOM)]);
    }

    public function updatePasswordHash(UserIdentity $user, string $passwordHash, DateTimeImmutable $now): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE app_users SET password_hash = :password_hash, updated_at = :now WHERE id = :id
SQL);
        $statement->execute([
            'id' => $user->id,
            'password_hash' => $passwordHash,
            'now' => $now->format(DATE_ATOM),
        ]);
    }

    private static function identifier(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
