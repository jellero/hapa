<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Bootstrap;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationFlowTest extends TestCase
{
    private PDO $connection;
    private string $userId;
    private string $email;
    private const PASSWORD = 'A-secure-test-password-2026!';

    protected function setUp(): void
    {
        $configuration = ConfigurationLoader::load();
        $connections = new ConnectionFactory($configuration->database);
        $this->connection = $connections->create();
        $this->email = sprintf('auth-%s@example.test', bin2hex(random_bytes(6)));
        $hash = password_hash(self::PASSWORD, PASSWORD_ARGON2ID);
        $user = (new UserRepository($connections))->create(
            $this->email,
            'Integration Administrator',
            'administrator',
            $hash,
            (new SystemClock())->now(),
        );
        $this->userId = $user->id;
    }

    protected function tearDown(): void
    {
        $this->connection->prepare('DELETE FROM audit_logs WHERE actor_id = :id OR entity_id = :id')
            ->execute(['id' => $this->userId]);
        $this->connection->prepare('DELETE FROM app_users WHERE id = :id')->execute(['id' => $this->userId]);
    }

    public function testLoginRotatesTheSessionAndLogoutRevokesIt(): void
    {
        $kernel = Bootstrap::initialize(dirname(__DIR__, 2))->kernel();

        $loginForm = $kernel->handle(Request::create('/login'));
        self::assertSame(Response::HTTP_OK, $loginForm->getStatusCode());
        $anonymousCookie = self::sessionCookie($loginForm);
        $anonymousToken = (string) $anonymousCookie->getValue();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $anonymousToken);
        $matches = [];
        self::assertSame(1, preg_match(
            '/name="_csrf_token" value="([a-f0-9]{64})"/',
            (string) $loginForm->getContent(),
            $matches,
        ));
        $loginCsrfToken = $matches[1] ?? '';
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $loginCsrfToken);

        $login = Request::create('/login', 'POST', [
            'email' => $this->email,
            'password' => self::PASSWORD,
            '_csrf_token' => $loginCsrfToken,
            'next' => '/ui',
        ], ['hapa_session' => $anonymousToken]);
        $loginResponse = $kernel->handle($login);
        self::assertSame(Response::HTTP_SEE_OTHER, $loginResponse->getStatusCode());
        self::assertSame('/ui', $loginResponse->headers->get('Location'));
        $authenticatedCookie = self::sessionCookie($loginResponse);
        $authenticatedToken = (string) $authenticatedCookie->getValue();
        self::assertNotSame($anonymousToken, $authenticatedToken);
        self::assertTrue($authenticatedCookie->isHttpOnly());
        self::assertSame('lax', $authenticatedCookie->getSameSite());

        $dashboard = $kernel->handle(Request::create(
            '/ui',
            'GET',
            cookies: ['hapa_session' => $authenticatedToken],
        ));
        self::assertSame(Response::HTTP_OK, $dashboard->getStatusCode());
        self::assertStringContainsString('Integration Administrator', (string) $dashboard->getContent());

        $logout = $kernel->handle(Request::create('/logout', 'POST', [
            '_csrf_token' => hash_hmac('sha256', 'logout', $authenticatedToken),
        ], ['hapa_session' => $authenticatedToken]));
        self::assertSame(Response::HTTP_SEE_OTHER, $logout->getStatusCode());
        self::assertSame('/login', $logout->headers->get('Location'));

        $afterLogout = $kernel->handle(Request::create(
            '/ui',
            'GET',
            cookies: ['hapa_session' => $authenticatedToken],
        ));
        self::assertSame(Response::HTTP_SEE_OTHER, $afterLogout->getStatusCode());
    }

    private static function sessionCookie(Response $response): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'hapa_session') {
                return $cookie;
            }
        }

        self::fail('Cookie di sessione HAPA non trovato.');
    }
}
