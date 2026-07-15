<?php

declare(strict_types=1);

namespace Hapa\Core;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

final readonly class Kernel
{
    public function __construct(private RouteCollection $routes)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            $context = new RequestContext();
            $context->fromRequest($request);

            $parameters = (new UrlMatcher($this->routes, $context))->matchRequest($request);
            $controller = $parameters['_controller'] ?? null;

            if (!is_callable($controller)) {
                return new JsonResponse(['error' => 'Controller non configurato'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $response = $controller($request, $parameters);

            return $response instanceof Response
                ? $response
                : new JsonResponse($response);
        } catch (ResourceNotFoundException) {
            return new JsonResponse(['error' => 'Risorsa non trovata'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $exception) {
            $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);

            return new JsonResponse(
                ['error' => 'Errore applicativo'] + ($debug ? ['detail' => $exception->getMessage()] : []),
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
