<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Http\HttpResponsePolicy;
use Hapa\Core\Kernel;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Stringable;
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
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString(
            "default-src 'self'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
        self::assertStringContainsString(
            "object-src 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
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

    public function testItReturnsBadRequestForANonScalarQueryParameter(): void
    {
        $routes = new RouteCollection();
        $routes->add('search', new Route('/search', [
            '_controller' => static fn (Request $request): array => [
                'query' => $request->query->getString('q'),
            ],
        ], methods: ['GET']));

        $response = $this->kernel($routes)->handle(Request::create('/search?q[]=invalid'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"error":"Richiesta non valida"}', $response->getContent());
    }

    public function testItDoesNotExposeExceptionDetailsWhenDebugIsDisabled(): void
    {
        $routes = $this->failingRoutes();
        $response = $this->kernel($routes)->handle(Request::create('/failure'));

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('database-password', (string) $response->getContent());
    }

    public function testItDoesNotWriteExceptionMessageToProductionLogs(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            /** @param array<string, mixed> $context */
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $this->kernel($this->failingRoutes(), $logger)->handle(Request::create('/failure'));

        self::assertCount(1, $logger->records);
        self::assertArrayNotHasKey('message', $logger->records[0]['context']);
        self::assertStringNotContainsString(
            'database-password-must-not-leak',
            json_encode($logger->records[0]['context'], JSON_THROW_ON_ERROR),
        );
    }

    private function failingRoutes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('failure', new Route('/failure', [
            '_controller' => static function (): never {
                throw new RuntimeException('database-password-must-not-leak');
            },
        ]));

        return $routes;
    }

    private function kernel(RouteCollection $routes, ?LoggerInterface $logger = null): Kernel
    {
        return new Kernel($routes, $logger ?? new NullLogger(), false, new HttpResponsePolicy());
    }
}
