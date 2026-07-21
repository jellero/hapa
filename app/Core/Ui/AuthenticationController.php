<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Audit\AuditLogger;
use Hapa\Core\Security\CredentialAuthenticator;
use Hapa\Core\Security\SessionManager;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\WebSession;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticationController
{
    public function __construct(
        private UiController $ui,
        private CredentialAuthenticator $authenticator,
        private SessionManager $sessions,
        private AuditLogger $audit,
    ) {
    }

    public function login(Request $request): Response
    {
        $session = $request->attributes->get('security_session');
        if (!$session instanceof WebSession) {
            return new Response('Sessione di sicurezza non disponibile.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $email = strtolower(trim($request->request->getString('email')));
        $password = $request->request->getString('password');
        $next = self::safeNext($request->request->getString('next'));
        $user = $this->authenticatedUser($request, $email, $password);
        if ($user instanceof Response) {
            return $user;
        }

        $this->sessions->authenticate($session, $user, $request->request->getBoolean('remember'));
        $this->audit->record(
            $user,
            'security.login_succeeded',
            'app_user',
            $user->id,
            null,
            ['role' => $user->role],
            $request->attributes->getString('correlation_id'),
        );

        return new RedirectResponse($next, Response::HTTP_SEE_OTHER);
    }

    private function authenticatedUser(Request $request, string $email, string $password): UserIdentity|Response
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $password === '') {
            return $this->failed($request, $email, 'Inserisci email e password valide.');
        }
        $user = $this->authenticator->authenticate($email, $password);
        if ($user !== null) {
            return $user;
        }
        $this->audit->record(null, 'security.login_failed', 'user_login', hash('sha256', $email), null, ['result' => 'rejected'], $request->attributes->getString('correlation_id'));
        return $this->failed($request, $email, 'Credenziali non valide.');
    }

    public function logout(Request $request): Response
    {
        $session = $request->attributes->get('security_session');
        if ($session instanceof WebSession) {
            $user = $session->user;
            if ($user !== null) {
                $this->audit->record(
                    $user,
                    'security.logout',
                    'app_user',
                    $user->id,
                    null,
                    ['result' => 'session_revoked'],
                    $request->attributes->getString('correlation_id'),
                );
            }
            $this->sessions->invalidate($session);
        }

        return new RedirectResponse('/login', Response::HTTP_SEE_OTHER);
    }

    private function failed(Request $request, string $email, string $message): Response
    {
        $request->attributes->set('login_email', $email);
        $request->attributes->set('login_error', $message);
        $request->attributes->set('login_status', Response::HTTP_UNPROCESSABLE_ENTITY);

        return $this->ui->login($request);
    }

    private static function safeNext(string $next): string
    {
        return str_starts_with($next, '/ui') && !str_starts_with($next, '//') ? $next : '/ui';
    }
}
