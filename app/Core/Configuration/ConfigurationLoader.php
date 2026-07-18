<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final class ConfigurationLoader
{
    public static function load(): ConfigurationSet
    {
        $application = new ApplicationConfig(
            EnvironmentReader::value('APP_ENV', 'development'),
            self::boolean('APP_DEBUG', 'false'),
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
        $rabbitMq = new RabbitMqConfig(
            self::boolean('RABBITMQ_ENABLED', 'false'),
            EnvironmentReader::value('RABBITMQ_HOST', 'rabbitmq'),
            self::integer('RABBITMQ_PORT', '5672'),
            EnvironmentReader::value('RABBITMQ_VHOST', '/'),
            EnvironmentReader::value('RABBITMQ_USERNAME', 'hapa'),
            EnvironmentReader::secret('RABBITMQ_PASSWORD', ''),
            EnvironmentReader::value('RABBITMQ_EXCHANGE', 'hapa.events'),
            self::decimal('RABBITMQ_CONNECT_TIMEOUT', '5.0'),
            self::decimal('RABBITMQ_READ_WRITE_TIMEOUT', '30.0'),
            self::integer('RABBITMQ_HEARTBEAT', '30'),
        );
        $rabbitMqConsumer = new RabbitMqConsumerConfig(
            self::boolean('RABBITMQ_CONSUMER_ENABLED', 'false'),
            EnvironmentReader::value('RABBITMQ_CONSUMER_HOST', $rabbitMq->host),
            self::integer('RABBITMQ_CONSUMER_PORT', (string) $rabbitMq->port),
            EnvironmentReader::value('RABBITMQ_CONSUMER_VHOST', $rabbitMq->vhost),
            EnvironmentReader::value('RABBITMQ_CONSUMER_USERNAME', $rabbitMq->username),
            EnvironmentReader::secret('RABBITMQ_CONSUMER_PASSWORD', $rabbitMq->password),
            EnvironmentReader::value('RABBITMQ_CONSUMER_EXCHANGE', 'hapa.events'),
            EnvironmentReader::value('RABBITMQ_CONSUMER_DEAD_EXCHANGE', 'hapa.dead'),
            EnvironmentReader::value('RABBITMQ_CONSUMER_QUEUE', 'hapa.inbound.events'),
            EnvironmentReader::value('RABBITMQ_CONSUMER_DEAD_QUEUE', 'hapa.inbound.dead'),
            self::list('RABBITMQ_CONSUMER_BINDINGS', 'integration.transport.#,space.catalog.item.observed,marketplace.order.observed'),
            self::decimal('RABBITMQ_CONSUMER_CONNECT_TIMEOUT', '5.0'),
            self::decimal('RABBITMQ_CONSUMER_READ_WRITE_TIMEOUT', '30.0'),
            self::integer('RABBITMQ_CONSUMER_HEARTBEAT', '30'),
            self::integer('RABBITMQ_CONSUMER_MAX_ATTEMPTS', '5'),
        );
        $outboxRelay = new OutboxRelayConfig(
            EnvironmentReader::value('OUTBOX_RELAY_WORKER_ID', 'hapa-relay-' . (gethostname() ?: 'worker')),
            self::integer('OUTBOX_RELAY_BATCH_SIZE', '50'),
            self::integer('OUTBOX_RELAY_LOCK_TIMEOUT_SECONDS', '300'),
            self::integer('OUTBOX_RELAY_RETRY_BASE_SECONDS', '30'),
            self::integer('OUTBOX_RELAY_RETRY_MAX_SECONDS', '3600'),
        );
        $automationAdmin = new AutomationAdminConfig(
            EnvironmentReader::value(
                'AUTOMATION_ADMIN_API_URL',
                $application->isProduction() ? 'https://hapa-automation-admin-api' : 'http://hapa-automation-admin-api:8091',
            ),
            EnvironmentReader::secret('AUTOMATION_ADMIN_API_TOKEN', 'hapa-automation-local-admin-token-change-me'),
            self::decimal('AUTOMATION_ADMIN_API_TIMEOUT', '10.0'),
            $application->isProduction(),
        );

        if ($application->isProduction()) {
            if ($proxy->trustedProxies === []) {
                throw new RuntimeException('TRUSTED_PROXIES deve essere configurato in produzione.');
            }

            self::assertProductionSecret('DB_PASSWORD', $database->password);
            self::assertProductionSecret('REDIS_PASSWORD', $redis->password);
            if ($rabbitMq->enabled) {
                self::assertProductionSecret('RABBITMQ_PASSWORD', $rabbitMq->password);
            }
            if ($rabbitMqConsumer->enabled) {
                self::assertProductionSecret(
                    'RABBITMQ_CONSUMER_PASSWORD',
                    $rabbitMqConsumer->password,
                );
            }
            self::assertProductionSecret('AUTOMATION_ADMIN_API_TOKEN', $automationAdmin->accessToken);
        }

        return new ConfigurationSet(
            $application,
            $database,
            $redis,
            $proxy,
            $integration,
            $rabbitMq,
            $rabbitMqConsumer,
            $outboxRelay,
            $automationAdmin,
        );
    }

    private static function boolean(string $name, string $default): bool
    {
        $value = trim(EnvironmentReader::value($name, $default));
        $boolean = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($boolean === null) {
            throw new RuntimeException(sprintf('%s non è un booleano valido.', $name));
        }

        return $boolean;
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

    /** @return list<string> */
    private static function list(string $name, string $default): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', EnvironmentReader::value($name, $default)),
        ), static fn (string $value): bool => $value !== ''));
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
