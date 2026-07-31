<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use JsonException;
use PDO;
use PDOStatement;
use stdClass;

final class DecryptingPdoStatement extends PDOStatement
{
    protected function __construct(private readonly PDO $connection)
    {
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        return $this->decrypt(parent::fetch($mode, $cursorOrientation, $cursorOffset));
    }

    /** @return array<mixed> */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        /** @var array<mixed> $rows */
        $rows = parent::fetchAll($mode, ...$args);
        $decrypted = $this->decrypt($rows);

        return is_array($decrypted) ? $decrypted : [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->decrypt(parent::fetchColumn($column));
    }

    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        $value = parent::fetchObject($class, $constructorArgs);
        if ($value === false) {
            return false;
        }
        $decrypted = $this->decrypt($value);

        return is_object($decrypted) ? $decrypted : $value;
    }

    private function decrypt(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->decrypt($item);
            }

            return $value;
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = $this->decrypt($item);
            }

            return $value;
        }
        if (!is_string($value) || $value === '') {
            return $value;
        }
        if (str_starts_with($value, 'hapa:v1:')) {
            return $this->decryptText($value);
        }

        $trimmed = ltrim($value);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return $value;
        }
        try {
            $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $value;
        }
        if (!is_array($decoded) || !is_string($decoded['_hapa_pii'] ?? null)) {
            return $value;
        }

        return $this->decryptJson($value);
    }

    private function decryptText(string $ciphertext): string
    {
        $statement = $this->connection->prepare('SELECT hapa_pii_decrypt(:ciphertext)');
        $statement->execute(['ciphertext' => $ciphertext]);
        $plaintext = $statement->fetchColumn();

        return is_string($plaintext) ? $plaintext : '';
    }

    private function decryptJson(string $ciphertext): string
    {
        $statement = $this->connection->prepare(
            'SELECT hapa_pii_decrypt_json(CAST(:ciphertext AS JSONB))::TEXT',
        );
        $statement->execute(['ciphertext' => $ciphertext]);
        $plaintext = $statement->fetchColumn();

        return is_string($plaintext) ? $plaintext : '{}';
    }
}
