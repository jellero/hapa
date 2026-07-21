<?php

declare(strict_types=1);

use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Ui\AuthenticationController;
use Hapa\Core\Ui\CatalogReviewController;
use Hapa\Core\Ui\CustomerController;
use Hapa\Core\Ui\IntegrationConfigurationController;
use Hapa\Core\Ui\PricingRuleController;
use Hapa\Core\Ui\SpacePurchaseController;
use Hapa\Core\Ui\UiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @param array<string, callable> $controllers
 */
$registerIntegrationRoutes = static function (
    RouteCollection $routes,
    UiController $ui,
    string $accountId,
    array $controllers,
): void {
    $routes->add('ui_integrations', new Route('/ui/integrations', ['_controller' => $ui->integrations(...), '_permission' => 'integrations.view'], methods: ['GET']));
    $actions = [
        ['ui_integration_create', '/ui/integrations', 'create', 'integrations.manage', 'integration.create', []],
        ['ui_integration_update', '/ui/integrations/{accountId}', 'update', 'integrations.manage', 'integration.update.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_retire', '/ui/integrations/{accountId}/retire', 'retire', 'integrations.manage', 'integration.retire.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_secrets_replace', '/ui/integrations/{accountId}/secrets', 'replace_secrets', 'integrations.secrets.manage', 'integration.secrets.replace.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_secrets_revoke', '/ui/integrations/{accountId}/secrets/revoke', 'revoke_secrets', 'integrations.secrets.manage', 'integration.secrets.revoke.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_configuration_sync', '/ui/integrations/{accountId}/configuration/sync', 'sync_configuration', 'integrations.manage', 'integration.configuration.sync.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_status_refresh', '/ui/integrations/{accountId}/status/refresh', 'refresh_status', 'integrations.manage', 'integration.status.refresh.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_status_change', '/ui/integrations/{accountId}/status', 'change_status', 'integrations.manage', 'integration.status.change.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_connection_test', '/ui/integrations/{accountId}/connection-test', 'test_connection', 'integrations.manage', 'integration.connection-test.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_orders_import', '/ui/integrations/{accountId}/orders/import', 'import_orders', 'integrations.manage', 'integration.orders.import.{accountId}', ['accountId' => $accountId]],
        ['ui_integration_catalog_sync', '/ui/integrations/{accountId}/catalog/sync', 'sync_catalog', 'integrations.manage', 'integration.catalog.sync.{accountId}', ['accountId' => $accountId]],
    ];

    foreach ($actions as [$name, $path, $controller, $permission, $csrfAction, $requirements]) {
        $routes->add($name, new Route($path, [
            '_controller' => $controllers[$controller],
            '_permission' => $permission,
            '_csrf_action' => $csrfAction,
        ], requirements: $requirements, methods: ['POST']));
    }
};

$registerHealthRoutes = static function (
    RouteCollection $routes,
    ReadinessCheck $readiness,
    ApplicationConfig $application,
): void {
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

            return new JsonResponse($payload, $result['ready'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
        }, '_public' => true],
        methods: ['GET'],
    ));
};

return static function (array $services) use ($registerIntegrationRoutes, $registerHealthRoutes): RouteCollection {
    /** @var UiController $ui */ $ui = $services['ui'];
    /** @var ReadinessCheck $readiness */ $readiness = $services['readiness'];
    /** @var ApplicationConfig $application */ $application = $services['application'];
    $authentication = $services['authentication'] ?? null;
    $integrationConfiguration = $services['integration_configuration'] ?? null;
    $pricingRules = $services['pricing_rules'] ?? null;
    $catalogReview = $services['catalog_review'] ?? null;
    $customers = $services['customers'] ?? null;
    $spacePurchases = $services['space_purchases'] ?? null;
    $routes = new RouteCollection();
    $positiveId = '[1-9][0-9]*'; $customerId = '[A-Za-z0-9._-]{3,64}'; $orderId = '[^/]{1,160}';
    $unavailableAuthentication = static fn (): JsonResponse => new JsonResponse(
        ['error' => 'Autenticazione non configurata'],
        Response::HTTP_SERVICE_UNAVAILABLE,
    );
    $resolveController = static fn (?object $service, string $method): callable => $service === null
        ? $unavailableAuthentication
        : $service->{$method}(...);
    $loginController = $resolveController($authentication, 'login');
    $logoutController = $resolveController($authentication, 'logout');
    $createIntegrationController = $resolveController($integrationConfiguration, 'create');
    $updateIntegrationController = $resolveController($integrationConfiguration, 'update');
    $retireIntegrationController = $resolveController($integrationConfiguration, 'retire');
    $replaceIntegrationSecretsController = $resolveController($integrationConfiguration, 'replaceSecrets');
    $revokeIntegrationSecretsController = $resolveController($integrationConfiguration, 'revokeSecrets');
    $syncIntegrationConfigurationController = $resolveController($integrationConfiguration, 'synchronizeConfiguration');
    $refreshIntegrationStatusController = $resolveController($integrationConfiguration, 'refreshTechnicalStatus');
    $changeIntegrationStatusController = $resolveController($integrationConfiguration, 'changeStatus');
    $testIntegrationConnectionController = $resolveController($integrationConfiguration, 'testConnection');
    $importIntegrationOrdersController = $resolveController($integrationConfiguration, 'importOrders');
    $synchronizeIntegrationCatalogController = $resolveController($integrationConfiguration, 'synchronizeCatalog');
    $integrationControllers = [
        'create' => $createIntegrationController, 'update' => $updateIntegrationController, 'retire' => $retireIntegrationController,
        'replace_secrets' => $replaceIntegrationSecretsController, 'revoke_secrets' => $revokeIntegrationSecretsController,
        'sync_configuration' => $syncIntegrationConfigurationController, 'refresh_status' => $refreshIntegrationStatusController,
        'change_status' => $changeIntegrationStatusController, 'test_connection' => $testIntegrationConnectionController,
        'import_orders' => $importIntegrationOrdersController, 'sync_catalog' => $synchronizeIntegrationCatalogController,
    ];
    $createPricingController = $resolveController($pricingRules, 'create');
    $updatePricingController = $resolveController($pricingRules, 'update');
    $retirePricingController = $resolveController($pricingRules, 'retire');
    $reviewCatalogController = $resolveController($catalogReview, 'review');
    $updateCatalogAvailabilityController = $resolveController($catalogReview, 'updateAvailability');
    $createCustomerController = $resolveController($customers, 'create');
    $updateCustomerController = $resolveController($customers, 'update');
    $archiveCustomerController = $resolveController($customers, 'archive');
    $generateSpacePurchaseController = $resolveController($spacePurchases, 'generate');

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
    ], requirements: ['customerId' => $customerId], methods: ['POST']));
    $routes->add('ui_customer_archive', new Route('/ui/customers/{customerId}/archive', [
        '_controller' => $archiveCustomerController,
        '_permission' => 'customers.manage',
        '_csrf_action' => 'customer.archive.{customerId}',
    ], requirements: ['customerId' => $customerId], methods: ['POST']));
    $routes->add('ui_customer_detail', new Route(
        '/ui/customers/{customerId}',
        ['_controller' => $ui->customerDetail(...), '_permission' => 'customers.view'],
        requirements: ['customerId' => $customerId],
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
    ], requirements: ['ruleId' => $positiveId], methods: ['POST']));
    $routes->add('ui_pricing_retire', new Route('/ui/catalog/pricing-rules/{ruleId}/retire', [
        '_controller' => $retirePricingController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'pricing.retire.{ruleId}',
    ], requirements: ['ruleId' => $positiveId], methods: ['POST']));
    $routes->add('ui_catalog_review', new Route('/ui/catalog/items/{itemId}/review', [
        '_controller' => $reviewCatalogController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'catalog.review.{itemId}',
    ], requirements: ['itemId' => $positiveId], methods: ['POST']));
    $routes->add('ui_catalog_availability_update', new Route('/ui/catalog/items/{itemId}/availability', [
        '_controller' => $updateCatalogAvailabilityController,
        '_permission' => 'catalog.manage',
        '_csrf_action' => 'catalog.availability.{itemId}',
    ], requirements: ['itemId' => $positiveId], methods: ['POST']));
    $routes->add('ui_order_detail', new Route(
        '/ui/orders/{orderId}',
        ['_controller' => $ui->orderDetail(...), '_permission' => 'orders.view'],
        requirements: ['orderId' => $orderId],
        methods: ['GET'],
    ));
    $routes->add('ui_order_space_purchase', new Route('/ui/orders/{orderId}/space-purchase', [
        '_controller' => $generateSpacePurchaseController,
        '_permission' => 'orders.manage',
        '_csrf_action' => 'order.space-purchase.{orderId}',
    ], requirements: ['orderId' => $orderId], methods: ['POST']));
    $routes->add('ui_picking', new Route('/ui/picking', ['_controller' => $ui->picking(...), '_permission' => 'orders.view'], methods: ['GET']));
    $routes->add('ui_shipments', new Route('/ui/shipments', ['_controller' => $ui->shipments(...), '_permission' => 'shipping.view'], methods: ['GET']));
    $registerIntegrationRoutes($routes, $ui, $positiveId, $integrationControllers);
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

    $registerHealthRoutes($routes, $readiness, $application);

    return $routes;
};
