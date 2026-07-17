<?php

declare(strict_types=1);

namespace Hapa\Composition;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Configuration\ConfigurationSet;
use Hapa\Core\Configuration\DatabaseConfig;
use Hapa\Core\Configuration\IntegrationConfig;
use Hapa\Core\Configuration\OutboxRelayConfig;
use Hapa\Core\Configuration\ProxyConfig;
use Hapa\Core\Configuration\RabbitMqConfig;
use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use Hapa\Core\Configuration\RedisConfig;
use Hapa\Core\Console\InboxConsumeCommand;
use Hapa\Core\Console\OutboxRelayCommand;
use Hapa\Core\Console\SystemCheckCommand;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Database\SchemaManifest;
use Hapa\Core\Database\TransactionManager;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Http\HttpResponsePolicy;
use Hapa\Core\Kernel;
use Hapa\Core\KernelFactory;
use Hapa\Core\Logging\LoggerFactory;
use Hapa\Core\Messaging\InboxConsumerFactory;
use Hapa\Core\Messaging\InboundMessageHandlerRegistry;
use Hapa\Core\Messaging\MessagePublisher;
use Hapa\Core\Messaging\RabbitMqPublisher;
use Hapa\Core\Messaging\TransportProbeHandler;
use Hapa\Core\Outbox\OutboxEnvelopeFactory;
use Hapa\Core\Outbox\OutboxRelayFactory;
use Hapa\Core\Outbox\OutboxRepository;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\OrderRepository;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
use Hapa\Modules\Space\Application\SpaceCatalogConsumeCommand;
use Hapa\Modules\Space\Application\SpaceCatalogObservationHandler;
use Hapa\Modules\Space\Infrastructure\Messaging\RabbitMqSpaceCatalogConsumer;
use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class ContainerFactory
{
    public function create(string $basePath, ConfigurationSet $configuration): ContainerBuilder
    {
        $container = $this->build($basePath, $configuration);
        $container->compile(true);

        return $container;
    }

    public function build(string $basePath, ConfigurationSet $configuration): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('hapa.base_path', $basePath);

        $container->setDefinition(ApplicationConfig::class, new Definition(ApplicationConfig::class, [
            $configuration->application->name,
            $configuration->application->debug,
            $configuration->application->appUrl,
            $configuration->application->timezone,
            $configuration->application->logLevel,
        ]));
        $container->setDefinition(DatabaseConfig::class, new Definition(DatabaseConfig::class, [
            $configuration->database->host,
            $configuration->database->port,
            $configuration->database->database,
            $configuration->database->username,
            $configuration->database->password,
            $configuration->database->connectTimeout,
        ]));
        $container->setDefinition(RedisConfig::class, new Definition(RedisConfig::class, [
            $configuration->redis->host,
            $configuration->redis->port,
            $configuration->redis->password,
            $configuration->redis->connectTimeout,
        ]));
        $container->setDefinition(ProxyConfig::class, new Definition(ProxyConfig::class, [
            $configuration->proxy->trustedProxies,
        ]));
        $container->setDefinition(IntegrationConfig::class, new Definition(IntegrationConfig::class, [
            $configuration->integration->connectTimeout,
            $configuration->integration->requestTimeout,
            $configuration->integration->maximumResponseBytes,
        ]));
        $container->setDefinition(RabbitMqConfig::class, new Definition(RabbitMqConfig::class, [
            $configuration->rabbitMq->enabled,
            $configuration->rabbitMq->host,
            $configuration->rabbitMq->port,
            $configuration->rabbitMq->vhost,
            $configuration->rabbitMq->username,
            $configuration->rabbitMq->password,
            $configuration->rabbitMq->exchange,
            $configuration->rabbitMq->connectTimeout,
            $configuration->rabbitMq->readWriteTimeout,
            $configuration->rabbitMq->heartbeat,
        ]));
        $container->setDefinition(
            RabbitMqConsumerConfig::class,
            new Definition(RabbitMqConsumerConfig::class, [
                $configuration->rabbitMqConsumer->enabled,
                $configuration->rabbitMqConsumer->host,
                $configuration->rabbitMqConsumer->port,
                $configuration->rabbitMqConsumer->vhost,
                $configuration->rabbitMqConsumer->username,
                $configuration->rabbitMqConsumer->password,
                $configuration->rabbitMqConsumer->exchange,
                $configuration->rabbitMqConsumer->deadExchange,
                $configuration->rabbitMqConsumer->queue,
                $configuration->rabbitMqConsumer->deadQueue,
                $configuration->rabbitMqConsumer->bindings,
                $configuration->rabbitMqConsumer->connectTimeout,
                $configuration->rabbitMqConsumer->readWriteTimeout,
                $configuration->rabbitMqConsumer->heartbeat,
                $configuration->rabbitMqConsumer->maximumAttempts,
            ]),
        );
        $container->setDefinition(OutboxRelayConfig::class, new Definition(OutboxRelayConfig::class, [
            $configuration->outboxRelay->workerId,
            $configuration->outboxRelay->batchSize,
            $configuration->outboxRelay->lockTimeoutSeconds,
            $configuration->outboxRelay->retryBaseSeconds,
            $configuration->outboxRelay->retryMaximumSeconds,
        ]));

        $container->register(SystemClock::class);
        $container->setAlias(Clock::class, SystemClock::class)->setPublic(false);
        $container->register(ConnectionFactory::class)
            ->setArguments([new Reference(DatabaseConfig::class)]);
        $container->setDefinition(PDO::class, (new Definition(PDO::class))
            ->setFactory([new Reference(ConnectionFactory::class), 'create']));
        $container->register(PdoTransactionManager::class)
            ->setArguments([new Reference(PDO::class)]);
        $container->setAlias(TransactionManager::class, PdoTransactionManager::class)->setPublic(false);

        // HAPA conserva e pubblica soltanto la propria transactional outbox.
        // Scheduler, retry provider, adapter e proiezioni operative appartengono
        // al servizio separato jellero/hapa-automation.
        $container->register(PostgresOutboxRepository::class)
            ->setArguments([new Reference(PDO::class)]);
        $container->setAlias(OutboxRepository::class, PostgresOutboxRepository::class)->setPublic(false);
        $container->register(RabbitMqPublisher::class)
            ->setArguments([new Reference(RabbitMqConfig::class)]);
        $container->setAlias(MessagePublisher::class, RabbitMqPublisher::class)->setPublic(false);
        $container->register(OutboxEnvelopeFactory::class);
        $container->register(OutboxRelayFactory::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(MessagePublisher::class),
                new Reference(OutboxEnvelopeFactory::class),
                new Reference(Clock::class),
                new Reference(OutboxRelayConfig::class),
            ]);

        // La inbox HAPA è idempotente e deny-by-default. I nuovi eventi
        // automation → HAPA richiedono sempre un handler esplicito.
        $container->register(TransportProbeHandler::class);
        $container->setDefinition(
            InboundMessageHandlerRegistry::class,
            new Definition(InboundMessageHandlerRegistry::class, [[
                new Reference(TransportProbeHandler::class),
            ]]),
        );
        $container->register(InboxConsumerFactory::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(InboundMessageHandlerRegistry::class),
                new Reference(Clock::class),
                new Reference(RabbitMqConsumerConfig::class),
            ]);

        $container->register(OrderEventOutboxMapper::class);
        $container->register(PriceCalculator::class);
        $container->register(SpaceCatalogObservationHandler::class)
            ->setArguments([
                new Reference(PDO::class),
                new Reference(TransactionManager::class),
            ]);
        $container->register(RabbitMqSpaceCatalogConsumer::class)
            ->setArguments([new Reference(RabbitMqConfig::class)]);
        $container->register(SpaceCatalogConsumeCommand::class)
            ->setArguments([
                new Reference(RabbitMqSpaceCatalogConsumer::class),
                new Reference(SpaceCatalogObservationHandler::class),
                new Reference(RabbitMqConfig::class),
            ])
            ->setPublic(true);
        $container->register(PostgresOrderRepository::class)
            ->setArguments([
                new Reference(PDO::class),
                new Reference(TransactionManager::class),
                new Reference(OutboxRepository::class),
                new Reference(OrderEventOutboxMapper::class),
            ]);
        $container->setAlias(OrderRepository::class, PostgresOrderRepository::class)->setPublic(false);

        $container->register(ReadinessCheck::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(RedisConfig::class),
                SchemaManifest::load($basePath . '/config/schema.php')->minimumVersion,
            ]);
        $container->register(LoggerFactory::class)
            ->setArguments([new Reference(ApplicationConfig::class)]);
        $container->setDefinition(LoggerInterface::class, (new Definition())
            ->setFactory([new Reference(LoggerFactory::class), 'create']));
        $container->register(HttpResponsePolicy::class);
        $container->register(ViewRenderer::class)
            ->setArguments([$basePath . '/templates']);
        $container->register(UiController::class)
            ->setArguments([
                new Reference(ViewRenderer::class),
                $configuration->application->name,
            ]);
        $container->register(KernelFactory::class)
            ->setArguments([
                new Reference(UiController::class),
                new Reference(ReadinessCheck::class),
                new Reference(ApplicationConfig::class),
                new Reference(LoggerInterface::class),
                new Reference(HttpResponsePolicy::class),
            ]);
        $container->setDefinition(Kernel::class, (new Definition())
            ->setFactory([new Reference(KernelFactory::class), 'create'])
            ->setArguments([$basePath])
            ->setPublic(true));
        $container->register(SystemCheckCommand::class)
            ->setArguments([new Reference(ReadinessCheck::class)])
            ->setPublic(true);
        $container->register(OutboxRelayCommand::class)
            ->setArguments([
                new Reference(OutboxRelayFactory::class),
                new Reference(RabbitMqConfig::class),
            ])
            ->setPublic(true);
        $container->register(InboxConsumeCommand::class)
            ->setArguments([
                new Reference(InboxConsumerFactory::class),
                new Reference(RabbitMqConsumerConfig::class),
            ])
            ->setPublic(true);

        return $container;
    }
}
