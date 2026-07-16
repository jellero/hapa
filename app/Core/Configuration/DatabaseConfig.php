<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final readonly class DatabaseConfig
{
    public string $host;
    public string $database;
    public string $username;

    public function __construct(
        string $host,
        public int $port,
        string $database,
        string $username,
        public string $password,
        public int $connectTimeout,
    ) {
        $this->host = self::identifier($host, 'DB_HOST', '/^[A-Za-z0-9_.:-]+$/D');
        $this->database = self::identifier($database, 'DB_DATABASE', '/^[A-Za-z0-9_.-]+$/D');
        $this->username = self::identifier($username, 'DB_USERNAME', '/^[A-Za-z0-9_.-]+$/D');

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('DB_PORT non valido.');
        }

        if ($connectTimeout < 1 || $connectTimeout > 30) {
            throw new RuntimeException('DB_CONNECT_TIMEOUT deve essere compreso tra 1 e 30 secondi.');
        }
    }

    private static function identifier(string $value, string $name, string $pattern): string
    {
        $normalized = trim($value);
        if ($normalized === '' || !preg_match($pattern, $normalized)) {
            throw new RuntimeException(sprintf('%s contiene caratteri non ammessi.', $name));
        }

        return $normalized;
    }
}
