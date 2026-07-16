<?php

declare(strict_types=1);

namespace Hapa\Core\Http;

use Symfony\Component\HttpFoundation\Response;

final class HttpResponsePolicy
{
    public function apply(Response $response, string $correlationId): Response
    {
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), display-capture=(), geolocation=(), microphone=(), payment=(), usb=()',
        );
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'none'; connect-src 'self'; font-src 'self'; "
            . "form-action 'self'; frame-ancestors 'none'; frame-src 'none'; img-src 'self' data:; "
            . "manifest-src 'self'; object-src 'none'; script-src 'self'; style-src 'self'",
        );
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
