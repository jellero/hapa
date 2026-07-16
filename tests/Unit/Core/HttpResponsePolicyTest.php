<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Http\HttpResponsePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class HttpResponsePolicyTest extends TestCase
{
    public function testItHardensResponsesOutsideTheKernel(): void
    {
        $response = (new HttpResponsePolicy())->apply(
            new Response('unavailable', Response::HTTP_SERVICE_UNAVAILABLE),
            'bootstrap-failure-id',
        );

        self::assertSame('bootstrap-failure-id', $response->headers->get('X-Correlation-ID'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame('none', $response->headers->get('X-Permitted-Cross-Domain-Policies'));
        self::assertStringContainsString(
            "object-src 'none'",
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }
}
