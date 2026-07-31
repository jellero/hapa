<?php

declare(strict_types=1);

namespace Hapa\Core\Cache;

use Hapa\Core\Configuration\RedisConfig;
use JsonException;
use Redis;
use Throwable;

final class ReadModelCache
{
    private ?Redis $connection = null;
    private bool $unavailable = false;

    public function __construct(private readonly RedisConfig $config)
    {
    }

    /** @template T of array<mixed>
     *  @param callable(): T $loader
     *  @return T
     */
    public function remember(string $key, int $ttlSeconds, callable $loader): array
    {
        $redis = $this->connection();
        if ($redis !== null) {
            try {
                $cached = $redis->get($key);
                if (is_string($cached)) {
                    $decoded = json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        /** @var T $decoded */
                        return $decoded;
                    }
                }
            } catch (Throwable) {
                $this->unavailable = true;
            }
        }

        $value = $loader();
        if ($redis !== null && !$this->unavailable) {
            try {
                $redis->setEx(
                    $key,
                    max(1, $ttlSeconds),
                    json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                );
            } catch (JsonException) {
                return $value;
            } catch (Throwable) {
                $this->unavailable = true;
            }
        }

        return $value;
    }

    public function forget(string $key): void
    {
        $redis = $this->connection();
        if ($redis === null) {
            return;
        }

        try {
            $redis->del($key);
        } catch (Throwable) {
            $this->unavailable = true;
        }
    }

    private function connection(): ?Redis
    {
        if ($this->unavailable || !class_exists(Redis::class)) {
            return null;
        }
        if ($this->connection instanceof Redis) {
            return $this->connection;
        }

        try {
            $redis = new Redis();
            if (!$redis->connect($this->config->host, $this->config->port, $this->config->connectTimeout)) {
                $this->unavailable = true;
                return null;
            }
            if ($this->config->password !== '' && !$redis->auth($this->config->password)) {
                $this->unavailable = true;
                return null;
            }
            $this->connection = $redis;

            return $redis;
        } catch (Throwable) {
            $this->unavailable = true;
            return null;
        }
    }
}
