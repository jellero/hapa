<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('home', new Route(
    '/',
    ['_controller' => static fn (Request $request): JsonResponse => new JsonResponse([
        'application' => 'HAPA',
        'status' => 'operational',
        'environment' => $_ENV['APP_ENV'] ?? 'development',
    ])],
    methods: ['GET'],
));

$routes->add('health', new Route(
    '/health',
    ['_controller' => static fn (): JsonResponse => new JsonResponse([
        'status' => 'ok',
        'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
    ])],
    methods: ['GET'],
));

return $routes;
