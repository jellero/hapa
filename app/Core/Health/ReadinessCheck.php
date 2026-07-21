<?php

declare(strict_types=1);

namespace Hapa\Core\Health;

use Hapa\Core\Configuration\RedisConfig;
use Hapa\Core\Database\ConnectionFactory;
use Redis;
use Throwable;

final readonly class ReadinessCheck
{
    public function __construct(
        private ConnectionFactory $connections,
        private RedisConfig $redis,
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
        if (!class_exists(Redis::class)) {
            return false;
        }

        $redis = new Redis();

        try {
            $connected = $redis->connect(
                $this->redis->host,
                $this->redis->port,
                $this->redis->connectTimeout,
            );

            $authenticated = $connected && $this->authenticateRedis($redis);
            return $authenticated && $redis->ping() !== false;
        } catch (Throwable) {
            return false;
        } finally {
            try {
                $redis->close();
            } catch (Throwable) {
                // Closing a failed or already closed health-check connection is best effort.
            }
        }
    }

    private function authenticateRedis(Redis $redis): bool
    {
        return $this->redis->password === '' || $redis->auth($this->redis->password);
    }
}
