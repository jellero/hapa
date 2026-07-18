<?php

declare(strict_types=1);

namespace Hapa\Composition;

use Hapa\Core\Audit\AuditLogger;
use Hapa\Core\Audit\AuditReadModel;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Configuration\AutomationAdminConfig;
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
use Hapa\Core\Console\UserCreateCommand;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Database\SchemaManifest;
use Hapa\Core\Database\TransactionManager;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Http\HttpResponsePolicy;
use Hapa\Core\Integration\IntegrationAccountConfiguration;
use Hapa\Core\Integration\IntegrationAccountRepository;
use Hapa\Core\Integration\AutomationSecretClient;
use Hapa\Core\Integration\ProviderSecretFields;
use Hapa\Core\Integration\ProviderSecretGateway;
use Hapa\Core\Integration\ProviderConfigurationGateway;
use Hapa\Core\Kernel;
use Hapa\Core\KernelFactory;
use Hapa\Core\Logging\LoggerFactory;
use Hapa\Core\Logging\SensitiveDataRedactor;
use Hapa\Core\Messaging\InboxConsumerFactory;
use Hapa\Core\Messaging\InboundMessageHandlerRegistryFactory;
use Hapa\Core\Messaging\MessagePublisher;
use Hapa\Core\Messaging\RabbitMqPublisher;
use Hapa\Core\Outbox\OutboxEnvelopeFactory;
use Hapa\Core\Outbox\OutboxRelayFactory;
use Hapa\Core\Outbox\OutboxRepository;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Core\Outbox\ProviderCommandPayloadValidator;
use Hapa\Core\Observability\RuntimeOverview;
use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Security\CredentialAuthenticator;
use Hapa\Core\Security\SessionManager;
use Hapa\Core\Security\UserRepository;
use Hapa\Core\Ui\AuthenticationController;
use Hapa\Core\Ui\CatalogProductManagement;
use Hapa\Core\Ui\CatalogReviewController;
use Hapa\Core\Ui\CatalogOverview;
use Hapa\Core\Ui\CustomerOverview;
use Hapa\Core\Ui\CustomerController;
use Hapa\Core\Ui\CustomerManagement;
use Hapa\Core\Ui\OrderOverview;
use Hapa\Core\Ui\IntegrationConfigurationController;
use Hapa\Core\Ui\PricingRuleController;
use Hapa\Core\Ui\PricingRuleManagement;
use Hapa\Core\Ui\PricingPreview;
use Hapa\Core\Ui\ShipmentOverview;
use Hapa\Core\Ui\SpacePurchaseController;
use Hapa\Core\Ui\SpacePurchaseManagement;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Catalog\Contract\CatalogOfferRecalculator;
use Hapa\Modules\Catalog\Application\CatalogReadModel;
use Hapa\Modules\Catalog\Application\CatalogProductReviewService;
use Hapa\Modules\Catalog\Application\MarketplaceOfferRecalculator;
use Hapa\Modules\Catalog\Application\PricingRuleService;
use Hapa\Modules\Catalog\Application\PricingPreviewService;
use Hapa\Modules\Customers\Application\CustomerReadModel;
use Hapa\Modules\Customers\Application\CustomerService;
use Hapa\Modules\Orders\Application\OrderReadModel;
use Hapa\Modules\Shipping\Application\ShipmentReadModel;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\OrderRepository;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
use Hapa\Modules\Procurement\Application\AutomaticSpacePurchaseGenerator;
use Hapa\Modules\Procurement\Application\SpacePurchaseBackfillCommand;
use Hapa\Modules\Procurement\Application\SpacePurchaseGenerationService;
use Hapa\Modules\Procurement\Contract\AutomaticPurchaseGenerator as AutomaticPurchaseGeneratorContract;
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
        $container->setDefinition(AutomationAdminConfig::class, new Definition(AutomationAdminConfig::class, [
            $configuration->automationAdmin->baseUrl,
            $configuration->automationAdmin->accessToken,
            $configuration->automationAdmin->timeoutSeconds,
            $configuration->application->isProduction(),
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
        $container->register(ProviderCommandPayloadValidator::class);
        $container->register(ProviderCommandFactory::class)
            ->setArguments([
                new Reference(ProviderCommandPayloadValidator::class),
                new Reference(Clock::class),
            ]);
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
        $container->register(HapaInboundMessageHandlerRegistryFactory::class);
        $container->setAlias(
            InboundMessageHandlerRegistryFactory::class,
            HapaInboundMessageHandlerRegistryFactory::class,
        )->setPublic(false);
        $container->register(InboxConsumerFactory::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(InboundMessageHandlerRegistryFactory::class),
                new Reference(Clock::class),
                new Reference(RabbitMqConsumerConfig::class),
            ]);

        $container->register(OrderEventOutboxMapper::class);
        $container->register(PriceCalculator::class);
        $container->register(MarketplaceOfferRecalculator::class)
            ->setArguments([
                new Reference(PriceCalculator::class),
                new Reference(Clock::class),
                new Reference(ProviderCommandFactory::class),
            ]);
        $container->setAlias(CatalogOfferRecalculator::class, MarketplaceOfferRecalculator::class)->setPublic(false);
        $container->register(PricingPreviewService::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(PriceCalculator::class),
                new Reference(Clock::class),
            ]);
        $container->setAlias(PricingPreview::class, PricingPreviewService::class)->setPublic(false);
        $container->register(CatalogReadModel::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->setAlias(CatalogOverview::class, CatalogReadModel::class)->setPublic(false);
        $container->register(CustomerReadModel::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->setAlias(CustomerOverview::class, CustomerReadModel::class)->setPublic(false);
        $container->register(CustomerService::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(Clock::class),
                new Reference(SensitiveDataRedactor::class),
            ]);
        $container->setAlias(CustomerManagement::class, CustomerService::class)->setPublic(false);
        $container->register(OrderReadModel::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->setAlias(OrderOverview::class, OrderReadModel::class)->setPublic(false);
        $container->register(ShipmentReadModel::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->setAlias(ShipmentOverview::class, ShipmentReadModel::class)->setPublic(false);
        $container->register(CatalogProductReviewService::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(Clock::class),
                new Reference(MarketplaceOfferRecalculator::class),
            ]);
        $container->setAlias(CatalogProductManagement::class, CatalogProductReviewService::class)->setPublic(false);
        $container->register(PricingRuleService::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(Clock::class),
                new Reference(MarketplaceOfferRecalculator::class),
            ]);
        $container->setAlias(PricingRuleManagement::class, PricingRuleService::class)->setPublic(false);
        $container->register(SpaceCatalogObservationHandler::class)
            ->setArguments([
                new Reference(PDO::class),
                new Reference(TransactionManager::class),
                new Reference(CatalogOfferRecalculator::class),
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
        $container->register(AutomaticSpacePurchaseGenerator::class)
            ->setArguments([
                new Reference(PDO::class),
                new Reference(OutboxRepository::class),
                new Reference(ProviderCommandFactory::class),
            ]);
        $container->setAlias(AutomaticPurchaseGeneratorContract::class, AutomaticSpacePurchaseGenerator::class)->setPublic(false);
        $container->register(SpacePurchaseGenerationService::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(ProviderCommandFactory::class),
            ]);
        $container->setAlias(SpacePurchaseManagement::class, SpacePurchaseGenerationService::class)->setPublic(false);

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
        $container->register(SensitiveDataRedactor::class);
        $container->register(UserRepository::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->register(CredentialAuthenticator::class)
            ->setArguments([
                new Reference(UserRepository::class),
                new Reference(Clock::class),
            ]);
        $container->register(SessionManager::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(Clock::class),
                $configuration->application->isProduction()
                    || str_starts_with($configuration->application->appUrl, 'https://'),
            ]);
        $container->register(AuthorizationPolicy::class);
        $container->register(AuditLogger::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(Clock::class),
                new Reference(SensitiveDataRedactor::class),
            ]);
        $container->register(AuditReadModel::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->register(RuntimeOverview::class)
            ->setArguments([new Reference(ConnectionFactory::class)]);
        $container->register(IntegrationAccountConfiguration::class);
        $container->register(IntegrationAccountRepository::class)
            ->setArguments([
                new Reference(ConnectionFactory::class),
                new Reference(Clock::class),
            ]);
        $container->register(ProviderSecretFields::class);
        $container->register(AutomationSecretClient::class)
            ->setArguments([new Reference(AutomationAdminConfig::class)]);
        $container->setAlias(ProviderSecretGateway::class, AutomationSecretClient::class)->setPublic(false);
        $container->setAlias(ProviderConfigurationGateway::class, AutomationSecretClient::class)->setPublic(false);
        $container->register(UiController::class)
            ->setArguments([
                new Reference(ViewRenderer::class),
                $configuration->application->name,
                new Reference(CatalogOverview::class),
                new Reference(IntegrationAccountRepository::class),
                new Reference(IntegrationAccountConfiguration::class),
                new Reference(AuditReadModel::class),
                new Reference(RuntimeOverview::class),
                new Reference(PricingRuleManagement::class),
                new Reference(CustomerOverview::class),
                new Reference(OrderOverview::class),
                new Reference(AuthorizationPolicy::class),
                new Reference(PricingPreview::class),
                new Reference(ShipmentOverview::class),
                new Reference(ProviderSecretFields::class),
            ]);
        $container->register(CustomerController::class)
            ->setArguments([new Reference(CustomerManagement::class)]);
        $container->register(AuthenticationController::class)
            ->setArguments([
                new Reference(UiController::class),
                new Reference(CredentialAuthenticator::class),
                new Reference(SessionManager::class),
                new Reference(AuditLogger::class),
            ]);
        $container->register(IntegrationConfigurationController::class)
            ->setArguments([
                new Reference(IntegrationAccountConfiguration::class),
                new Reference(IntegrationAccountRepository::class),
                new Reference(ProviderSecretGateway::class),
                new Reference(ProviderSecretFields::class),
                new Reference(ProviderConfigurationGateway::class),
                new Reference(SpacePurchaseManagement::class),
                new Reference(CatalogProductManagement::class),
            ]);
        $container->register(SpacePurchaseController::class)
            ->setArguments([new Reference(SpacePurchaseManagement::class)]);
        $container->register(PricingRuleController::class)
            ->setArguments([new Reference(PricingRuleManagement::class)]);
        $container->register(CatalogReviewController::class)
            ->setArguments([new Reference(CatalogProductManagement::class)]);
        $container->register(KernelFactory::class)
            ->setArguments([
                new Reference(UiController::class),
                new Reference(ReadinessCheck::class),
                new Reference(ApplicationConfig::class),
                new Reference(LoggerInterface::class),
                new Reference(HttpResponsePolicy::class),
                new Reference(AuthenticationController::class),
                new Reference(SessionManager::class),
                new Reference(AuthorizationPolicy::class),
                new Reference(IntegrationConfigurationController::class),
                new Reference(PricingRuleController::class),
                new Reference(CatalogReviewController::class),
                new Reference(CustomerController::class),
                new Reference(SpacePurchaseController::class),
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
        $container->register(UserCreateCommand::class)
            ->setArguments([
                new Reference(UserRepository::class),
                new Reference(Clock::class),
            ])
            ->setPublic(true);
        $container->register(SpacePurchaseBackfillCommand::class)
            ->setArguments([new Reference(SpacePurchaseManagement::class)])
            ->setPublic(true);

        return $container;
    }
}
