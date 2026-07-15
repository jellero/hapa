<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use Hapa\Core\Configuration\Environment;
use PDO;
use RuntimeException;

final class ConnectionFactory
{
    public function create(): PDO
    {
        $host = Environment::value('DB_HOST', 'postgres');
        $port = (int) Environment::value('DB_PORT', '5432');
        $database = Environment::value('DB_DATABASE', 'hapa');
        $username = Environment::value('DB_USERNAME', 'hapa');
        $password = Environment::secret('DB_PASSWORD', '');
        $connectTimeout = max(1, (int) Environment::value('DB_CONNECT_TIMEOUT', '5'));

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('DB_PORT non valido.');
        }

        foreach (['DB_HOST' => $host, 'DB_DATABASE' => $database, 'DB_USERNAME' => $username] as $name => $value) {
            if (!preg_match('/^[A-Za-z0-9_.:-]+$/D', $value)) {
                throw new RuntimeException(sprintf('%s contiene caratteri non ammessi.', $name));
            }
        }

        $pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=%d;application_name=hapa',
                $host,
                $port,
                $database,
                $connectTimeout,
            ),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ],
        );
        $pdo->exec("SET TIME ZONE 'UTC'");

        return $pdo;
    }
}
