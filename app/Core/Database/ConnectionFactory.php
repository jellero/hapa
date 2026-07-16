<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use Hapa\Core\Configuration\DatabaseConfig;
use PDO;

final readonly class ConnectionFactory
{
    public function __construct(private DatabaseConfig $config)
    {
    }

    public function create(): PDO
    {
        return new PDO(
            sprintf(
                'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=%d;application_name=hapa',
                $this->config->host,
                $this->config->port,
                $this->config->database,
                $this->config->connectTimeout,
            ),
            $this->config->username,
            $this->config->password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ],
        );
    }
}
