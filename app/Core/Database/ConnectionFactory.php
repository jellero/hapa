<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use Hapa\Core\Configuration\DatabaseConfig;
use Hapa\Core\Security\PiiKeyProvider;
use PDO;

final readonly class ConnectionFactory
{
    public function __construct(private DatabaseConfig $config)
    {
    }

    public function create(): PDO
    {
        $connection = new PDO(
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

        $statement = $connection->prepare(<<<'SQL'
SELECT
    set_config('hapa.pii_key', :pii_key, false),
    set_config('hapa.pii_key_id', :pii_key_id, false)
SQL);
        $statement->execute([
            'pii_key' => PiiKeyProvider::passphrase(),
            'pii_key_id' => PiiKeyProvider::keyId(),
        ]);
        $connection->setAttribute(
            PDO::ATTR_STATEMENT_CLASS,
            [DecryptingPdoStatement::class, [$connection]],
        );

        return $connection;
    }
}
