<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use Hapa\Core\Security\PiiKeyProvider;
use JsonException;
use PDO;
use PDOStatement;
use stdClass;

final class DecryptingPdoStatement extends PDOStatement
{
    protected function __construct(private readonly PDO $connection)
    {
    }

    /** @param array<int|string, mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        return parent::execute($params === null ? null : $this->encodeBlindIndexParameters($params));
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        if (is_string($param) && is_string($value) && $this->usesBlindIndexParameter(ltrim($param, ':'))) {
            $value = self::emailBlindIndex($value);
        }

        return parent::bindValue($param, $value, $type);
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        return $this->decodeValue(parent::fetch($mode, $cursorOrientation, $cursorOffset));
    }

    /** @return array<mixed> */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        /** @var array<mixed> $rows */
        $rows = parent::fetchAll($mode, ...$args);
        $decoded = $this->decodeValue($rows);

        return is_array($decoded) ? $decoded : [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->decodeValue(parent::fetchColumn($column));
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int|string, mixed>
     */
    private function encodeBlindIndexParameters(array $params): array
    {
        foreach ($this->blindIndexParameterNames() as $name) {
            foreach ([$name, ':' . $name] as $key) {
                if (array_key_exists($key, $params) && is_string($params[$key])) {
                    $params[$key] = self::emailBlindIndex($params[$key]);
                }
            }
        }

        return $params;
    }

    private function usesBlindIndexParameter(string $name): bool
    {
        return in_array($name, $this->blindIndexParameterNames(), true);
    }

    /** @return list<string> */
    private function blindIndexParameterNames(): array
    {
        $patterns = [
            '/\bemail_normalized\s*=\s*:([A-Za-z_][A-Za-z0-9_]*)/i',
            '/:([A-Za-z_][A-Za-z0-9_]*)\s*=\s*\bemail_normalized\b/i',
        ];
        $names = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $this->queryString, $matches);
            foreach ($matches[1] ?? [] as $name) {
                if (is_string($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private static function emailBlindIndex(string $email): string
    {
        return hash_hmac(
            'sha256',
            mb_strtolower(trim($email), 'UTF-8'),
            PiiKeyProvider::passphrase(),
        );
    }

    private function decodeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->decodeValue($item);
            }

            return $value;
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = $this->decodeValue($item);
            }

            return $value;
        }
        if (!is_string($value) || $value === '') {
            return $value;
        }
        if (str_starts_with($value, 'hapa:v1:')) {
            return $this->decodeText($value);
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

        return $this->decodeJson($value);
    }

    private function decodeText(string $ciphertext): string
    {
        $statement = $this->connection->prepare('SELECT hapa_pii_decrypt(:ciphertext)');
        $statement->execute(['ciphertext' => $ciphertext]);
        $plaintext = $statement->fetchColumn();

        return is_string($plaintext) ? $plaintext : '';
    }

    private function decodeJson(string $ciphertext): string
    {
        $statement = $this->connection->prepare(
            'SELECT hapa_pii_decrypt_json(CAST(:ciphertext AS JSONB))::TEXT',
        );
        $statement->execute(['ciphertext' => $ciphertext]);
        $plaintext = $statement->fetchColumn();

        return is_string($plaintext) ? $plaintext : '{}';
    }
}
