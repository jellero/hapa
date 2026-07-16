<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

final readonly class ConfigurationSet
{
    public function __construct(
        public ApplicationConfig $application,
        public DatabaseConfig $database,
        public RedisConfig $redis,
        public ProxyConfig $proxy,
        public IntegrationConfig $integration,
        public RabbitMqConfig $rabbitMq,
        public OutboxRelayConfig $outboxRelay,
    ) {
    }
}
