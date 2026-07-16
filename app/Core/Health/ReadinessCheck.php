<?php

declare(strict_types=1);

namespace Hapa\Core\Health;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Database\ConnectionFactory;
use Redis;
use Throwable;

final readonly class ReadinessCheck
{
    public function __construct(
        private ConnectionFactory $connections,
        private int $minimumSchemaVersion,
    ) {
    }

    /** @return array{ready: bool, components: array{database: bool, redis: bool}} */
    public function check(): array
    {
        $databaseReady = $this->databaseReady();
        $redisReady = $this->redisReady();

        return [
            'ready' => $databaseReady && $redisReady,
            'components' => [
                'database' => $databaseReady,
                'redis' => $redisReady,
            ],
        ];
    }

    private function databaseReady(): bool
    {
        try {
            $pdo = $this->connections->create();
            $statement = $pdo->query("SELECT COALESCE(MAX(version), 0) FROM phinxlog");

            return $statement !== false
                && (int) $statement->fetchColumn() >= $this->minimumSchemaVersion;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        $redis = new Redis();

        try {
            $connected = $redis->connect(
                Environment::value('REDIS_HOST', 'redis'),
                (int) Environment::value('REDIS_PORT', '6379'),
                (float) Environment::value('REDIS_CONNECT_TIMEOUT', '2.0'),
            );

            if (!$connected) {
                return false;
            }

            $password = Environment::secret('REDIS_PASSWORD', '');
            if ($password !== '' && !$redis->auth($password)) {
                return false;
            }

            return $redis->ping() !== false;
        } catch (Throwable) {
            return false;
        } finally {
            try {
                $redis->close();
            } catch (Throwable) {
            }
        }
    }
}
