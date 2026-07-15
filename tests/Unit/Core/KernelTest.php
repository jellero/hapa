<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
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
        ], methods: ['GET']));

        $response = $this->kernel($routes)->handle(Request::create('/test'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"ok":true}', $response->getContent());
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $response->headers->get('X-Correlation-ID'));
    }

    public function testItReturnsNotFoundForUnknownRoutes(): void
    {
        $response = $this->kernel(new RouteCollection())->handle(Request::create('/missing'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testItReturnsMethodNotAllowed(): void
    {
        $routes = new RouteCollection();
        $routes->add('test', new Route('/test', [
            '_controller' => static fn (): JsonResponse => new JsonResponse(['ok' => true]),
        ], methods: ['GET']));

        $response = $this->kernel($routes)->handle(Request::create('/test', 'POST'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET', $response->headers->get('Allow'));
    }

    public function testItDoesNotExposeExceptionDetailsWhenDebugIsDisabled(): void
    {
        $routes = new RouteCollection();
        $routes->add('failure', new Route('/failure', [
            '_controller' => static function (): never {
                throw new RuntimeException('database-password-must-not-leak');
            },
        ]));

        $response = $this->kernel($routes)->handle(Request::create('/failure'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('database-password', (string) $response->getContent());
    }

    private function kernel(RouteCollection $routes): Kernel
    {
        return new Kernel($routes, new NullLogger(), false);
    }
}
