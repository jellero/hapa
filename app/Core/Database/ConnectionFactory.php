<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use PDO;

final class ConnectionFactory
{
    public function create(): PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'postgres';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $database = $_ENV['DB_DATABASE'] ?? 'hapa';
        $username = $_ENV['DB_USERNAME'] ?? 'hapa';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }
}
