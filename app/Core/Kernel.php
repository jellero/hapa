<?php

declare(strict_types=1);

namespace Hapa\Core;

use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

final readonly class Kernel
{
    public function __construct(
        private RouteCollection $routes,
        private LoggerInterface $logger,
        private bool $debug,
    ) {
    }

    public function handle(Request $request): Response
    {
        $correlationId = $this->correlationId($request);
        $request->attributes->set('correlation_id', $correlationId);

        try {
            $context = new RequestContext();
            $context->fromRequest($request);

            $parameters = (new UrlMatcher($this->routes, $context))->matchRequest($request);
            $controller = $parameters['_controller'] ?? null;

            if (!is_callable($controller)) {
                throw new LogicException('Controller non configurato.');
            }

            unset($parameters['_controller'], $parameters['_route']);
            $request->attributes->add($parameters);
            $result = $controller($request);
            $response = $result instanceof Response ? $result : new JsonResponse($result);
        } catch (ResourceNotFoundException) {
            $response = $this->problem('Risorsa non trovata', Response::HTTP_NOT_FOUND);
        } catch (MethodNotAllowedException $exception) {
            $response = $this->problem('Metodo non consentito', Response::HTTP_METHOD_NOT_ALLOWED);
            $response->headers->set('Allow', implode(', ', $exception->getAllowedMethods()));
        } catch (Throwable $exception) {
            $logContext = [
                'correlation_id' => $correlationId,
                'exception' => $exception::class,
                'exception_code' => (string) $exception->getCode(),
                'method' => $request->getMethod(),
                'path' => $request->getPathInfo(),
            ];

            if ($this->debug) {
                $logContext['message'] = $exception->getMessage();
            }

            $this->logger->error('Errore applicativo non gestito.', $logContext);

            $payload = ['error' => 'Errore applicativo'];
            if ($this->debug) {
                $payload['detail'] = $exception->getMessage();
            }

            $response = new JsonResponse($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->finalize($response, $correlationId);
    }

    private function problem(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private function finalize(Response $response, string $correlationId): Response
    {
        $response->headers->set('X-Correlation-ID', $correlationId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
        );

        if ($response instanceof JsonResponse) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function correlationId(Request $request): string
    {
        $provided = $request->headers->get('X-Correlation-ID');

        if (is_string($provided) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $provided)) {
            return $provided;
        }

        return bin2hex(random_bytes(16));
    }
}
