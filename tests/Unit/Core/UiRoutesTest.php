<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Bootstrap;
use Hapa\Core\KernelFactory;
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
        self::assertSame('/ui/orders', $routes->get('ui_orders')?->getPath());
        self::assertSame('/ui/orders/{orderId}', $routes->get('ui_order_detail')?->getPath());
        self::assertSame('/ui/picking', $routes->get('ui_picking')?->getPath());
        self::assertSame('/ui/shipments', $routes->get('ui_shipments')?->getPath());
        self::assertSame('/ui/automation', $routes->get('ui_automation')?->getPath());
        self::assertSame('/ui/integrations', $routes->get('ui_integrations')?->getPath());
        self::assertSame('/ui/users', $routes->get('ui_users')?->getPath());
        self::assertSame('/ui/audit', $routes->get('ui_audit')?->getPath());
        self::assertSame('/ui/settings', $routes->get('ui_settings')?->getPath());
        self::assertSame('/ui/profile', $routes->get('ui_profile')?->getPath());
        self::assertSame('/ui/{path}', $routes->get('ui_not_found')?->getPath());
    }

    public function testTheKernelServesTheDashboardWithSecurityHeaders(): void
    {
        $basePath = dirname(__DIR__, 3);
        $kernel = (new KernelFactory())->create($basePath, Bootstrap::initialize($basePath));
        $response = $kernel->handle(Request::create('/ui'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertStringContainsString('Centro operativo', (string) $response->getContent());
    }

    private function routes(): RouteCollection
    {
        $basePath = dirname(__DIR__, 3);
        /** @var \Closure(Bootstrap): RouteCollection $routeFactory */
        $routeFactory = require $basePath . '/config/routes.php';
        $routes = $routeFactory(Bootstrap::initialize($basePath));

        return $routes;
    }
}
