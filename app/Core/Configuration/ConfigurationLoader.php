<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final class ConfigurationLoader
{
    public static function load(): ConfigurationSet
    {
        $debugValue = trim(EnvironmentReader::value('APP_DEBUG', 'false'));
        $debug = filter_var($debugValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($debug === null) {
            throw new RuntimeException(sprintf('APP_DEBUG non valido: %s', $debugValue));
        }

        $application = new ApplicationConfig(
            EnvironmentReader::value('APP_ENV', 'development'),
            $debug,
            EnvironmentReader::value('APP_URL', 'http://localhost:8080'),
            EnvironmentReader::value('APP_TIMEZONE', 'Europe/Rome'),
            EnvironmentReader::value('LOG_LEVEL', 'info'),
        );
        $database = new DatabaseConfig(
            EnvironmentReader::value('DB_HOST', 'postgres'),
            self::integer('DB_PORT', '5432'),
            EnvironmentReader::value('DB_DATABASE', 'hapa'),
            EnvironmentReader::value('DB_USERNAME', 'hapa'),
            EnvironmentReader::secret('DB_PASSWORD', ''),
            self::integer('DB_CONNECT_TIMEOUT', '5'),
        );
        $redis = new RedisConfig(
            EnvironmentReader::value('REDIS_HOST', 'redis'),
            self::integer('REDIS_PORT', '6379'),
            EnvironmentReader::secret('REDIS_PASSWORD', ''),
            self::decimal('REDIS_CONNECT_TIMEOUT', '2.0'),
        );
        $proxy = new ProxyConfig(array_values(array_filter(array_map(
            'trim',
            explode(',', EnvironmentReader::value('TRUSTED_PROXIES', '')),
        ), static fn (string $value): bool => $value !== '')));
        $integration = new IntegrationConfig(
            self::decimal('INTEGRATION_CONNECT_TIMEOUT', '5.0'),
            self::decimal('INTEGRATION_REQUEST_TIMEOUT', '30.0'),
            self::integer('INTEGRATION_MAX_RESPONSE_BYTES', '2097152'),
        );

        if ($application->isProduction()) {
            if ($proxy->trustedProxies === []) {
                throw new RuntimeException('TRUSTED_PROXIES deve essere configurato in produzione.');
            }

            self::assertProductionSecret('DB_PASSWORD', $database->password);
            self::assertProductionSecret('REDIS_PASSWORD', $redis->password);
        }

        return new ConfigurationSet($application, $database, $redis, $proxy, $integration);
    }

    private static function integer(string $name, string $default): int
    {
        $value = trim(EnvironmentReader::value($name, $default));
        if (!preg_match('/^-?[0-9]+$/D', $value)) {
            throw new RuntimeException(sprintf('%s deve essere un numero intero.', $name));
        }

        return (int) $value;
    }

    private static function decimal(string $name, string $default): float
    {
        $value = trim(EnvironmentReader::value($name, $default));
        if (!is_numeric($value)) {
            throw new RuntimeException(sprintf('%s deve essere numerico.', $name));
        }

        $number = (float) $value;
        if (!is_finite($number)) {
            throw new RuntimeException(sprintf('%s non è finito.', $name));
        }

        return $number;
    }

    private static function assertProductionSecret(string $name, string $value): void
    {
        $normalized = strtolower($value);
        foreach (['replace_with_secret', 'change-me', 'changeme', 'local'] as $forbidden) {
            if (str_contains($normalized, $forbidden)) {
                throw new RuntimeException(sprintf('%s contiene un valore non ammesso in produzione.', $name));
            }
        }

        if (strlen($value) < 16) {
            throw new RuntimeException(sprintf('%s deve contenere almeno 16 caratteri in produzione.', $name));
        }
    }
}
