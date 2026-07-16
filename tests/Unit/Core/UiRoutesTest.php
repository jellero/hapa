<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Bootstrap;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\SchemaManifest;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

final class UiRoutesTest extends TestCase
{
    public function testItRegistersEveryUserInterfaceRoute(): void
    {
        $routes = $this->routes();

        self::assertSame('/login', $routes->get('login')?->getPath());
        self::assertSame('/password/recovery', $routes->get('password_recovery')?->getPath());
        self::assertSame('/ui', $routes->get('ui_dashboard')?->getPath());
        self::assertSame('/ui/customers', $routes->get('ui_customers')?->getPath());
        self::assertSame('/ui/customers/{customerId}', $routes->get('ui_customer_detail')?->getPath());
        self::assertSame('/ui/orders', $routes->get('ui_orders')?->getPath());
        self::assertSame('/ui/orders/{orderId}', $routes->get('ui_order_detail')?->getPath());
        self::assertSame('/ui/catalog', $routes->get('ui_catalog')?->getPath());
        self::assertSame('/ui/picking', $routes->get('ui_picking')?->getPath());
        self::assertSame('/ui/shipments', $routes->get('ui_shipments')?->getPath());
        self::assertNull($routes->get('ui_automation'));
        self::assertSame('/ui/integrations', $routes->get('ui_integrations')?->getPath());
        self::assertSame('/ui/users', $routes->get('ui_users')?->getPath());
        self::assertSame('/ui/audit', $routes->get('ui_audit')?->getPath());
        self::assertSame('/ui/settings', $routes->get('ui_settings')?->getPath());
        self::assertSame('/ui/profile', $routes->get('ui_profile')?->getPath());
        self::assertSame('/ui/{path}', $routes->get('ui_not_found')?->getPath());
    }

    public function testTheKernelServesTheProductRegistryPage(): void
    {
        $kernel = Bootstrap::initialize(dirname(__DIR__, 3))->kernel();
        $response = $kernel->handle(Request::create('/ui/catalog'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Da Space all’offerta marketplace', (string) $response->getContent());
        self::assertStringContainsString('Nuova regola di ricarico', (string) $response->getContent());
        self::assertStringContainsString('Stock Space', (string) $response->getContent());
    }

    public function testTheRemovedAutomationPathReturnsNotFound(): void
    {
        $kernel = Bootstrap::initialize(dirname(__DIR__, 3))->kernel();
        $response = $kernel->handle(Request::create('/ui/automation'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Pagina non trovata', (string) $response->getContent());
    }

    public function testTheKernelServesTheDashboardWithSecurityHeaders(): void
    {
        $kernel = Bootstrap::initialize(dirname(__DIR__, 3))->kernel();
        $response = $kernel->handle(Request::create('/ui'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertStringContainsString('Centro operativo', (string) $response->getContent());
    }

    public function testTheKernelServesCustomerMasterDataPages(): void
    {
        $kernel = Bootstrap::initialize(dirname(__DIR__, 3))->kernel();

        $collection = $kernel->handle(Request::create('/ui/customers'));
        self::assertSame(200, $collection->getStatusCode());
        self::assertStringContainsString('Nessun cliente disponibile', (string) $collection->getContent());

        $detail = $kernel->handle(Request::create('/ui/customers/CUST-0001'));
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('Cliente CUST-0001', (string) $detail->getContent());
        self::assertStringContainsString('Identità esterne', (string) $detail->getContent());
    }

    public function testTheKernelServesTheBrandedNotFoundPageForNestedUiPaths(): void
    {
        $kernel = Bootstrap::initialize(dirname(__DIR__, 3))->kernel();
        $response = $kernel->handle(Request::create('/ui/not/a/real/page'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertStringContainsString('Pagina non trovata', (string) $response->getContent());
    }

    private function routes(): RouteCollection
    {
        $basePath = dirname(__DIR__, 3);
        $configuration = ConfigurationLoader::load();
        /** @var \Closure(UiController, ReadinessCheck, \Hapa\Core\Configuration\ApplicationConfig): RouteCollection $routeFactory */
        $routeFactory = require $basePath . '/config/routes.php';

        return $routeFactory(
            new UiController(new ViewRenderer($basePath . '/templates'), $configuration->application->name),
            new ReadinessCheck(
                new ConnectionFactory($configuration->database),
                $configuration->redis,
                SchemaManifest::load($basePath . '/config/schema.php')->minimumVersion,
            ),
            $configuration->application,
        );
    }
}
