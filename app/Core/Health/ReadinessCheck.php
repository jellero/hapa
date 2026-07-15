<?php

declare(strict_types=1);

namespace Hapa\Core\Health;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Database\ConnectionFactory;
use Redis;
use Throwable;

final readonly class ReadinessCheck
{
    public function __construct(private ConnectionFactory $connections)
    {
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
            $statement = $this->connections->create()->query('SELECT 1');

            return $statement !== false && $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        try {
            $redis = new Redis();
            $connected = $redis->connect(
                Environment::value('REDIS_HOST', 'redis'),
                (int) Environment::value('REDIS_PORT', '6379'),
                (float) Environment::value('REDIS_CONNECT_TIMEOUT', '2.0'),
            );

            if (!$connected) {
                return false;
            }

            $password = Environment::value('REDIS_PASSWORD', '');
            if ($password !== '' && !$redis->auth($password)) {
                return false;
            }

            $ready = $redis->ping() !== false;
            $redis->close();

            return $ready;
        } catch (Throwable) {
            return false;
        }
    }
}
