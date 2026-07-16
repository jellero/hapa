<?php

declare(strict_types=1);

namespace Hapa\Composition;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Configuration\ConfigurationSet;
use Hapa\Core\Configuration\DatabaseConfig;
use Hapa\Core\Configuration\IntegrationConfig;
use Hapa\Core\Configuration\ProxyConfig;
use Hapa\Core\Configuration\RedisConfig;
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
use Hapa\Core\Outbox\OutboxRepository;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\OrderRepository;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
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

        $container->register(SystemClock::class);
        $container->setAlias(Clock::class, SystemClock::class)->setPublic(false);
        $container->register(ConnectionFactory::class)
            ->setArguments([new Reference(DatabaseConfig::class)]);
        $container->setDefinition(PDO::class, (new Definition(PDO::class))
            ->setFactory([new Reference(ConnectionFactory::class), 'create']));
        $container->register(PdoTransactionManager::class)
            ->setArguments([new Reference(PDO::class)]);
        $container->setAlias(TransactionManager::class, PdoTransactionManager::class)->setPublic(false);

        // HAPA conserva soltanto l'outbox transazionale dei propri eventi.
        // Scheduling, retry provider, consumer RabbitMQ e proiezioni operative
        // appartengono al servizio separato jellero/hapa-automation.
        $container->register(PostgresOutboxRepository::class)
            ->setArguments([new Reference(PDO::class)]);
        $container->setAlias(OutboxRepository::class, PostgresOutboxRepository::class)->setPublic(false);

        $container->register(OrderEventOutboxMapper::class);
        $container->register(PriceCalculator::class);
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

        return $container;
    }
}
