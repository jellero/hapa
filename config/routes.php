<?php

declare(strict_types=1);

use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Ui\AuthenticationController;
use Hapa\Core\Ui\CatalogReviewController;
use Hapa\Core\Ui\CustomerController;
use Hapa\Core\Ui\IntegrationConfigurationController;
use Hapa\Core\Ui\PricingRuleController;
use Hapa\Core\Ui\UiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (
    UiController $ui,
    ReadinessCheck $readiness,
    ApplicationConfig $application,
    ?AuthenticationController $authentication = null,
    ?IntegrationConfigurationController $integrationConfiguration = null,
    ?PricingRuleController $pricingRules = null,
    ?CatalogReviewController $catalogReview = null,
    ?CustomerController $customers = null,
): RouteCollection {
    $routes = new RouteCollection();
    $unavailableAuthentication = static fn (): JsonResponse => new JsonResponse(
        ['error' => 'Autenticazione non configurata'],
        Response::HTTP_SERVICE_UNAVAILABLE,
    );
    $loginController = $authentication instanceof AuthenticationController
        ? $authentication->login(...)
        : $unavailableAuthentication;
    $logoutController = $authentication instanceof AuthenticationController
        ? $authentication->logout(...)
        : $unavailableAuthentication;
    $createIntegrationController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->create(...)
        : $unavailableAuthentication;
    $updateIntegrationController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->update(...)
        : $unavailableAuthentication;
    $retireIntegrationController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->retire(...)
        : $unavailableAuthentication;
    $replaceIntegrationSecretsController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->replaceSecrets(...)
        : $unavailableAuthentication;
    $revokeIntegrationSecretsController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->revokeSecrets(...)
        : $unavailableAuthentication;
    $syncIntegrationConfigurationController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->synchronizeConfiguration(...)
        : $unavailableAuthentication;
    $refreshIntegrationStatusController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->refreshTechnicalStatus(...)
        : $unavailableAuthentication;
    $changeIntegrationStatusController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->changeStatus(...)
        : $unavailableAuthentication;
    $testIntegrationConnectionController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->testConnection(...)
        : $unavailableAuthentication;
    $importIntegrationOrdersController = $integrationConfiguration instanceof IntegrationConfigurationController
        ? $integrationConfiguration->importOrders(...)
        : $unavailableAuthentication;
    $createPricingController = $pricingRules instanceof PricingRuleController
        ? $pricingRules->create(...)
        : $unavailableAuthentication;
    $updatePricingController = $pricingRules instanceof PricingRuleController
        ? $pricingRules->update(...)
        : $unavailableAuthentication;
    $retirePricingController = $pricingRules instanceof PricingRuleController
        ? $pricingRules->retire(...)
        : $unavailableAuthentication;
    $reviewCatalogController = $catalogReview instanceof CatalogReviewController
        ? $catalogReview->review(...)
        : $unavailableAuthentication;
    $createCustomerController = $customers instanceof CustomerController
        ? $customers->create(...)
        : $unavailableAuthentication;
    $updateCustomerController = $customers instanceof CustomerController
        ? $customers->update(...)
        : $unavailableAuthentication;
    $archiveCustomerController = $customers instanceof CustomerController
        ? $customers->archive(...)
        : $unavailableAuthentication;

    $routes->add('home', new Route(
        '/',
        ['_controller' => static fn (Request $request): JsonResponse => new JsonResponse([
            'application' => 'HAPA',
            'status' => 'bootstrapped',
            'interface' => '/ui',
            'automation_service' => 'jellero/hapa-automation',
            'correlation_id' => $request->attributes->getString('correlation_id'),
        ]), '_public' => true],
        methods: ['GET'],
    ));

    $routes->add('login', new Route('/login', ['_controller' => $ui->login(...), '_public' => true, '_session' => true], methods: ['GET']));
    $routes->add('login_submit', new Route('/login', [
        '_controller' => $loginController,
        '_public' => true,
        '_session' => true,
        '_csrf_action' => 'login',
    ], methods: ['POST']));
    $routes->add('logout', new Route('/logout', [
        '_controller' => $logoutController,
        '_permission' => 'profile.view',
        '_csrf_action' => 'logout',
    ], methods: ['POST']));
    $routes->add('password_recovery', new Route('/password/recovery', ['_controller' => $ui->recovery(...), '_public' => true], methods: ['GET']));
    $routes->add('ui_dashboard', new Route('/ui', ['_controller' => $ui->dashboard(...), '_permission' => 'ui.view'], methods: ['GET']));
    $routes->add('ui_customers', new Route('/ui/customers', ['_controller' => $ui->customers(...), '_permission' => 'customers.view'], methods: ['GET']));
    $routes->add('ui_customer_create', new Route('/ui/customers', [
        '_controller' => $createCustomerController,
        '_permission' => 'customers.manage',
        '_csrf_action' => 'customer.create',
    ], methods: ['POST']));
    $routes->add('ui_customer_update', new Route('/ui/customers/{customerId}', [
        '_controller' => $updateCustomerController,
        '_permission' => 'customers.manage',
        '_csrf_action' => 'customer.update.{customerId}',
    ], requirements: ['customerId' => '[A-Za-z0-9._-]{3,64}'], methods: ['POST']));
    $routes->add('ui_customer_archive', new Route('/ui/customers/{customerId}/archive', [
        '_controller' => $archiveCustomerController,
        '_permission' => 'customers.manage',
        '_csrf_action' => 'customer.archive.{customerId}',
    ], requirements: ['customerId' => '[A-Za-z0-9._-]{3,64}'], methods: ['POST']));
    $routes->add('ui_customer_detail', new Route(
        '/ui/customers/{customerId}',
        ['_controller' => $ui->customerDetail(...), '_permission' => 'customers.view'],
        requirements: ['customerId' => '[A-Za-z0-9._-]{3,64}'],
        methods: ['GET'],
    ));
    $routes->add('ui_orders', new Route('/ui/orders', ['_controller' => $ui->orders(...), '_permission' => 'orders.view'], methods: ['GET']));
    $routes->add('ui_catalog', new Route('/ui/catalog', ['_controller' => $ui->catalog(...), '_permission' => 'catalog.view'], methods: ['GET']));
    $routes->add('ui_pricing_create', new Route('/ui/catalog/pricing-rules', [
        '_controller' => $createPricingController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'pricing.create',
    ], methods: ['POST']));
    $routes->add('ui_pricing_update', new Route('/ui/catalog/pricing-rules/{ruleId}', [
        '_controller' => $updatePricingController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'pricing.update.{ruleId}',
    ], requirements: ['ruleId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_pricing_retire', new Route('/ui/catalog/pricing-rules/{ruleId}/retire', [
        '_controller' => $retirePricingController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'pricing.retire.{ruleId}',
    ], requirements: ['ruleId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_catalog_review', new Route('/ui/catalog/items/{itemId}/review', [
        '_controller' => $reviewCatalogController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'catalog.review.{itemId}',
    ], requirements: ['itemId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_order_detail', new Route(
        '/ui/orders/{orderId}',
        ['_controller' => $ui->orderDetail(...), '_permission' => 'orders.view'],
        requirements: ['orderId' => '[^/]{1,160}'],
        methods: ['GET'],
    ));
    $routes->add('ui_picking', new Route('/ui/picking', ['_controller' => $ui->picking(...), '_permission' => 'orders.view'], methods: ['GET']));
    $routes->add('ui_shipments', new Route('/ui/shipments', ['_controller' => $ui->shipments(...), '_permission' => 'shipping.view'], methods: ['GET']));
    $routes->add('ui_integrations', new Route('/ui/integrations', ['_controller' => $ui->integrations(...), '_permission' => 'integrations.view'], methods: ['GET']));
    $routes->add('ui_integration_create', new Route('/ui/integrations', [
        '_controller' => $createIntegrationController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.create',
    ], methods: ['POST']));
    $routes->add('ui_integration_update', new Route('/ui/integrations/{accountId}', [
        '_controller' => $updateIntegrationController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.update.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_retire', new Route('/ui/integrations/{accountId}/retire', [
        '_controller' => $retireIntegrationController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.retire.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_secrets_replace', new Route('/ui/integrations/{accountId}/secrets', [
        '_controller' => $replaceIntegrationSecretsController,
        '_permission' => 'integrations.secrets.manage',
        '_csrf_action' => 'integration.secrets.replace.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_secrets_revoke', new Route('/ui/integrations/{accountId}/secrets/revoke', [
        '_controller' => $revokeIntegrationSecretsController,
        '_permission' => 'integrations.secrets.manage',
        '_csrf_action' => 'integration.secrets.revoke.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_configuration_sync', new Route('/ui/integrations/{accountId}/configuration/sync', [
        '_controller' => $syncIntegrationConfigurationController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.configuration.sync.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_status_refresh', new Route('/ui/integrations/{accountId}/status/refresh', [
        '_controller' => $refreshIntegrationStatusController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.status.refresh.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_status_change', new Route('/ui/integrations/{accountId}/status', [
        '_controller' => $changeIntegrationStatusController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.status.change.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_connection_test', new Route('/ui/integrations/{accountId}/connection-test', [
        '_controller' => $testIntegrationConnectionController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.connection-test.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_integration_orders_import', new Route('/ui/integrations/{accountId}/orders/import', [
        '_controller' => $importIntegrationOrdersController,
        '_permission' => 'integrations.manage',
        '_csrf_action' => 'integration.orders.import.{accountId}',
    ], requirements: ['accountId' => '[1-9][0-9]*'], methods: ['POST']));
    $routes->add('ui_users', new Route('/ui/users', ['_controller' => $ui->users(...), '_permission' => 'users.manage'], methods: ['GET']));
    $routes->add('ui_audit', new Route('/ui/audit', ['_controller' => $ui->audit(...), '_permission' => 'audit.view'], methods: ['GET']));
    $routes->add('ui_settings', new Route('/ui/settings', ['_controller' => $ui->settings(...), '_permission' => 'settings.manage'], methods: ['GET']));
    $routes->add('ui_profile', new Route('/ui/profile', ['_controller' => $ui->profile(...), '_permission' => 'profile.view'], methods: ['GET']));
    $routes->add('ui_not_found', new Route(
        '/ui/{path}',
        ['_controller' => $ui->notFound(...), '_permission' => 'ui.view'],
        requirements: ['path' => '.+'],
        methods: ['GET'],
    ));

    $routes->add('health_live', new Route(
        '/health/live',
        ['_controller' => static fn (Request $request): JsonResponse => new JsonResponse([
            'status' => 'ok',
            'correlation_id' => $request->attributes->getString('correlation_id'),
        ]), '_public' => true],
        methods: ['GET'],
    ));

    $routes->add('health_ready', new Route(
        '/health/ready',
        ['_controller' => static function (Request $request) use ($readiness, $application): JsonResponse {
            $result = $readiness->check();
            $payload = [
                'status' => $result['ready'] ? 'ready' : 'unavailable',
                'correlation_id' => $request->attributes->getString('correlation_id'),
            ];

            if (!$application->isProduction()) {
                $payload['components'] = $result['components'];
            }

            return new JsonResponse(
                $payload,
                $result['ready'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }, '_public' => true],
        methods: ['GET'],
    ));

    return $routes;
};
