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
        $session = null;

        try {
            $context = new RequestContext();
            $context->fromRequest($request);

            $parameters = (new UrlMatcher($this->routes, $context))->matchRequest($request);
            $controller = $parameters['_controller'] ?? null;

            if (!is_callable($controller)) {
                throw new LogicException('Controller non configurato.');
            }

            $public = ($parameters['_public'] ?? false) === true;
            $requiresSession = ($parameters['_session'] ?? false) === true || !$public;
            $permission = $parameters['_permission'] ?? null;
            $csrfAction = $parameters['_csrf_action'] ?? null;

            if ($requiresSession && $this->sessions !== null) {
                $session = $this->sessions->open($request);
                $request->attributes->set('security_session', $session);
                $request->attributes->set('current_user', $session->user);
            }

            if (!$public && $this->sessions !== null && $this->authorization !== null) {
                if (!$session instanceof WebSession || $session->user === null) {
                    throw new AuthenticationRequired('Autenticazione richiesta.');
                }
                if (!is_string($permission) || $permission === '' || !$this->authorization->allows($session->user, $permission)) {
                    throw new AccessDenied('Permesso negato.');
                }
            }

            if (is_string($csrfAction) && $csrfAction !== '' && $this->sessions !== null) {
                if (!$session instanceof WebSession) {
                    throw new InvalidCsrfToken('Sessione CSRF non disponibile.');
                }
                foreach ($parameters as $name => $value) {
                    if (is_string($name) && (is_string($value) || is_int($value))) {
                        $csrfAction = str_replace('{' . $name . '}', (string) $value, $csrfAction);
                    }
                }
                $providedToken = $request->headers->get('X-CSRF-Token', $request->request->getString('_csrf_token'));
                $this->sessions->verifyCsrf($session, $csrfAction, is_string($providedToken) ? $providedToken : '');
            }

            unset(
                $parameters['_controller'],
                $parameters['_route'],
                $parameters['_public'],
                $parameters['_session'],
                $parameters['_permission'],
                $parameters['_csrf_action'],
            );
            $request->attributes->add($parameters);
            $result = $controller($request);
            $response = $result instanceof Response ? $result : new JsonResponse($result);
        } catch (AuthenticationRequired) {
            $next = rawurlencode($request->getPathInfo());
            $response = str_starts_with($request->getPathInfo(), '/ui')
                ? new RedirectResponse('/login?next=' . $next, Response::HTTP_SEE_OTHER)
                : $this->problem('Autenticazione richiesta', Response::HTTP_UNAUTHORIZED);
        } catch (AccessDenied) {
            $response = $this->problem('Accesso negato', Response::HTTP_FORBIDDEN);
        } catch (InvalidCsrfToken) {
            $response = $this->problem('Richiesta scaduta o non valida', Response::HTTP_FORBIDDEN);
        } catch (ResourceNotFoundException) {
            $response = $this->problem('Risorsa non trovata', Response::HTTP_NOT_FOUND);
        } catch (MethodNotAllowedException $exception) {
            $response = $this->problem('Metodo non consentito', Response::HTTP_METHOD_NOT_ALLOWED);
            $response->headers->set('Allow', implode(', ', $exception->getAllowedMethods()));
        } catch (RequestUnexpectedValueException) {
            $response = $this->problem('Richiesta non valida', Response::HTTP_BAD_REQUEST);
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

        $response = $this->finalize($response, $correlationId);
        if ($session instanceof WebSession && $this->sessions !== null) {
            $this->sessions->attachCookie($response, $session);
        }

        return $response;
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
