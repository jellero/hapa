<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final readonly class RedisConfig
{
    public string $host;

    public function __construct(
        string $host,
        public int $port,
        public string $password,
        public float $connectTimeout,
    ) {
        $normalizedHost = trim($host);
        if ($normalizedHost === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/D', $normalizedHost)) {
            throw new RuntimeException('REDIS_HOST contiene caratteri non ammessi.');
        }

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('REDIS_PORT non valido.');
        }

        if ($connectTimeout <= 0 || $connectTimeout > 30) {
            throw new RuntimeException('REDIS_CONNECT_TIMEOUT deve essere maggiore di zero e non superare 30 secondi.');
        }

        $this->host = $normalizedHost;
    }
}
