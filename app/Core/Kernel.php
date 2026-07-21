<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Core\Http\HttpResponsePolicy;
use Hapa\Core\Security\AccessDenied;
use Hapa\Core\Security\AuthenticationRequired;
use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Security\InvalidCsrfToken;
use Hapa\Core\Security\SessionManager;
use Hapa\Core\Security\WebSession;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\UnexpectedValueException as RequestUnexpectedValueException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private HttpResponsePolicy $responsePolicy,
        private ?SessionManager $sessions = null,
        private ?AuthorizationPolicy $authorization = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $correlationId = $this->correlationId($request);
        $request->attributes->set('correlation_id', $correlationId);
        [$response, $session] = $this->dispatchSafely($request, $correlationId);

        $response = $this->finalize($response, $correlationId);
        if ($session instanceof WebSession && $this->sessions !== null) {
            $this->sessions->attachCookie($response, $session);
        }

        return $response;
    }

    /** @return array{Response, WebSession|null} */
    private function dispatchSafely(Request $request, string $correlationId): array
    {
        $session = null;
        try {
            return [$this->dispatch($request, $session), $session];
        } catch (Throwable $exception) {
            return [$this->exceptionResponse($exception, $request, $correlationId), $session];
        }
    }

    private function dispatch(Request $request, ?WebSession &$session): Response
    {
        $context = new RequestContext();
        $context->fromRequest($request);
        $parameters = (new UrlMatcher($this->routes, $context))->matchRequest($request);
        $controller = $parameters['_controller'] ?? null;
        if (!is_callable($controller)) {
            throw new LogicException('Controller non configurato.');
        }

        $public = ($parameters['_public'] ?? false) === true;
        $session = $this->openSession($request, ($parameters['_session'] ?? false) === true || !$public);
        $this->authorize($session, $public, $parameters['_permission'] ?? null);
        $this->verifyCsrf($request, $session, $parameters['_csrf_action'] ?? null, $parameters);
        $this->addRouteAttributes($request, $parameters);
        $result = $controller($request);

        return $result instanceof Response ? $result : new JsonResponse($result);
    }

    private function openSession(Request $request, bool $required): ?WebSession
    {
        if (!$required || $this->sessions === null) {
            return null;
        }

        $session = $this->sessions->open($request);
        $request->attributes->set('security_session', $session);
        $request->attributes->set('current_user', $session->user);

        return $session;
    }

    private function authorize(?WebSession $session, bool $public, mixed $permission): void
    {
        if ($public || $this->sessions === null || $this->authorization === null) {
            return;
        }
        if ($session?->user === null) {
            throw new AuthenticationRequired('Autenticazione richiesta.');
        }
        if (!is_string($permission) || $permission === '' || !$this->authorization->allows($session->user, $permission)) {
            throw new AccessDenied('Permesso negato.');
        }
    }

    /** @param array<string, mixed> $parameters */
    private function verifyCsrf(Request $request, ?WebSession $session, mixed $action, array $parameters): void
    {
        if (!is_string($action) || $action === '' || $this->sessions === null) {
            return;
        }
        if ($session === null) {
            throw new InvalidCsrfToken('Sessione CSRF non disponibile.');
        }

        foreach ($parameters as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) {
                $action = str_replace('{' . $name . '}', (string) $value, $action);
            }
        }
        $provided = $request->headers->get('X-CSRF-Token', $request->request->getString('_csrf_token'));
        $this->sessions->verifyCsrf($session, $action, is_string($provided) ? $provided : '');
    }

    /** @param array<string, mixed> $parameters */
    private function addRouteAttributes(Request $request, array $parameters): void
    {
        foreach (['_controller', '_route', '_public', '_session', '_permission', '_csrf_action'] as $internal) {
            unset($parameters[$internal]);
        }
        $request->attributes->add($parameters);
    }

    private function exceptionResponse(Throwable $exception, Request $request, string $correlationId): Response
    {
        return match (true) {
            $exception instanceof AuthenticationRequired => $this->authenticationRequired($request),
            $exception instanceof AccessDenied => $this->problem('Accesso negato', Response::HTTP_FORBIDDEN),
            $exception instanceof InvalidCsrfToken => $this->problem('Richiesta scaduta o non valida', Response::HTTP_FORBIDDEN),
            $exception instanceof ResourceNotFoundException => $this->problem('Risorsa non trovata', Response::HTTP_NOT_FOUND),
            $exception instanceof MethodNotAllowedException => $this->methodNotAllowed($exception),
            $exception instanceof RequestUnexpectedValueException => $this->problem('Richiesta non valida', Response::HTTP_BAD_REQUEST),
            default => $this->unexpectedError($exception, $request, $correlationId),
        };
    }

    private function authenticationRequired(Request $request): Response
    {
        return str_starts_with($request->getPathInfo(), '/ui')
            ? new RedirectResponse('/login?next=' . rawurlencode($request->getPathInfo()), Response::HTTP_SEE_OTHER)
            : $this->problem('Autenticazione richiesta', Response::HTTP_UNAUTHORIZED);
    }

    private function methodNotAllowed(MethodNotAllowedException $exception): Response
    {
        $response = $this->problem('Metodo non consentito', Response::HTTP_METHOD_NOT_ALLOWED);
        $response->headers->set('Allow', implode(', ', $exception->getAllowedMethods()));

        return $response;
    }

    private function unexpectedError(Throwable $exception, Request $request, string $correlationId): Response
    {
        $context = [
            'correlation_id' => $correlationId,
            'exception' => $exception::class,
            'exception_code' => (string) $exception->getCode(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ];
        if ($this->debug) {
            $context['message'] = $exception->getMessage();
        }
        $this->logger->error('Errore applicativo non gestito.', $context);

        return new JsonResponse(
            $this->debug
                ? ['error' => 'Errore applicativo', 'detail' => $exception->getMessage()]
                : ['error' => 'Errore applicativo'],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    private function problem(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private function finalize(Response $response, string $correlationId): Response
    {
        return $this->responsePolicy->apply($response, $correlationId);
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
