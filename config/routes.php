<?php

declare(strict_types=1);

use Hapa\Core\Bootstrap;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (Bootstrap $bootstrap): RouteCollection {
    $routes = new RouteCollection();
    $ui = new UiController(
        new ViewRenderer(dirname(__DIR__) . '/templates'),
        $bootstrap->environment->name,
    );

    $routes->add('home', new Route(
        '/',
        ['_controller' => static fn (Request $request): JsonResponse => new JsonResponse([
            'application' => 'HAPA',
            'status' => 'bootstrapped',
            'interface' => '/ui',
            'correlation_id' => $request->attributes->getString('correlation_id'),
        ])],
        methods: ['GET'],
    ));

    $routes->add('login', new Route(
        '/login',
        ['_controller' => $ui->login(...)],
        methods: ['GET'],
    ));

    $routes->add('password_recovery', new Route(
        '/password/recovery',
        ['_controller' => $ui->recovery(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_dashboard', new Route(
        '/ui',
        ['_controller' => $ui->dashboard(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_orders', new Route(
        '/ui/orders',
        ['_controller' => $ui->orders(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_order_detail', new Route(
        '/ui/orders/{orderId}',
        ['_controller' => $ui->orderDetail(...)],
        requirements: ['orderId' => '[^/]{1,160}'],
        methods: ['GET'],
    ));

    $routes->add('ui_picking', new Route(
        '/ui/picking',
        ['_controller' => $ui->picking(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_shipments', new Route(
        '/ui/shipments',
        ['_controller' => $ui->shipments(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_automation', new Route(
        '/ui/automation',
        ['_controller' => $ui->automation(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_integrations', new Route(
        '/ui/integrations',
        ['_controller' => $ui->integrations(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_users', new Route(
        '/ui/users',
        ['_controller' => $ui->users(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_audit', new Route(
        '/ui/audit',
        ['_controller' => $ui->audit(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_settings', new Route(
        '/ui/settings',
        ['_controller' => $ui->settings(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_profile', new Route(
        '/ui/profile',
        ['_controller' => $ui->profile(...)],
        methods: ['GET'],
    ));

    $routes->add('ui_not_found', new Route(
        '/ui/{path}',
        ['_controller' => $ui->notFound(...)],
        requirements: ['path' => '.+'],
        methods: ['GET'],
    ));

    $routes->add('health_live', new Route(
        '/health/live',
        ['_controller' => static fn (Request $request): JsonResponse => new JsonResponse([
            'status' => 'ok',
            'correlation_id' => $request->attributes->getString('correlation_id'),
        ])],
        methods: ['GET'],
    ));

    $routes->add('health_ready', new Route(
        '/health/ready',
        ['_controller' => static function (Request $request) use ($bootstrap): JsonResponse {
            $result = $bootstrap->readiness->check();
            $payload = [
                'status' => $result['ready'] ? 'ready' : 'unavailable',
                'correlation_id' => $request->attributes->getString('correlation_id'),
            ];

            if (!$bootstrap->environment->isProduction()) {
                $payload['components'] = $result['components'];
            }

            return new JsonResponse(
                $payload,
                $result['ready'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }],
        methods: ['GET'],
    ));

    return $routes;
};
