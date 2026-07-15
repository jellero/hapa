<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class KernelTest extends TestCase
{
    public function testItDispatchesAConfiguredRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('test', new Route('/test', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => true]),
        ]));

        $response = (new Kernel($routes))->handle(Request::create('/test'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"ok":true}', $response->getContent());
    }

    public function testItReturnsNotFoundForUnknownRoutes(): void
    {
        $response = (new Kernel(new RouteCollection()))->handle(Request::create('/missing'));

        self::assertSame(404, $response->getStatusCode());
    }
}
