<?php

declare(strict_types=1);

namespace Hapa\Core\Health;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Database\ConnectionFactory;
use Psr\Log\LoggerInterface;
use Redis;
use RuntimeException;
use Throwable;

final class ReadinessCheck
{
    /** @var array<string, int> */
    private static array $lastLogAt = [];

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly LoggerInterface $logger,
        private readonly string $expectedSchemaVersion,
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
            $table = $pdo->query("SELECT to_regclass('public.phinxlog')");
            if ($table === false || $table->fetchColumn() === null) {
                throw new RuntimeException('Schema migrations assente.');
            }

            $statement = $pdo->query('SELECT version FROM phinxlog ORDER BY version DESC LIMIT 1');
            $version = $statement === false ? false : $statement->fetchColumn();
            if (!is_string($version) && !is_int($version)) {
                throw new RuntimeException('Versione schema non disponibile.');
            }

            if ((int) $version < (int) $this->expectedSchemaVersion) {
                throw new RuntimeException('Schema applicativo non aggiornato.');
            }

            return true;
        } catch (Throwable $exception) {
            $this->logFailure('database', $exception);

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
                throw new RuntimeException('Connessione Redis non disponibile.');
            }

            $password = Environment::secret('REDIS_PASSWORD', '');
            if ($password !== '' && !$redis->auth($password)) {
                throw new RuntimeException('Autenticazione Redis fallita.');
            }

            if ($redis->ping() === false) {
                throw new RuntimeException('Ping Redis fallito.');
            }

            return true;
        } catch (Throwable $exception) {
            $this->logFailure('redis', $exception);

            return false;
        } finally {
            try {
                $redis->close();
            } catch (Throwable) {
            }
        }
    }

    private function logFailure(string $component, Throwable $exception): void
    {
        $now = time();
        $last = self::$lastLogAt[$component] ?? 0;
        if ($now - $last < 60) {
            return;
        }

        self::$lastLogAt[$component] = $now;
        $this->logger->warning('Componente readiness non disponibile.', [
            'component' => $component,
            'exception' => $exception::class,
            'exception_code' => (string) $exception->getCode(),
        ]);
    }
}
