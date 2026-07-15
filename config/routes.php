<?php

declare(strict_types=1);

use Hapa\Core\Bootstrap\ApplicationContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

return static function (ApplicationContext $context): RouteCollection {
    $routes = new RouteCollection();
    $production = $context->environment->isProduction();

    $routes->add('home', new Route(
        '/',
        ['_controller' => static fn (Request $request): JsonResponse => new JsonResponse([
            'application' => 'HAPA',
            'status' => 'bootstrapped',
            'correlation_id' => $request->attributes->getString('correlation_id'),
        ])],
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
        ['_controller' => static function (Request $request) use ($context, $production): JsonResponse {
            $result = $context->readiness->check();
            /** @var array<string, mixed> $payload */
            $payload = [
                'status' => $result['ready'] ? 'ready' : 'unavailable',
                'correlation_id' => $request->attributes->getString('correlation_id'),
            ];

            if (!$production) {
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
