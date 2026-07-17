<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

use DateInterval;
use DateTimeImmutable;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use PDO;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SessionManager
{
    private const COOKIE = 'hapa_session';

    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly Clock $clock,
        private readonly bool $secureCookie,
    ) {
    }

    public function open(Request $request): WebSession
    {
        $token = $request->cookies->get(self::COOKIE);
        if (is_string($token) && preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
            $session = $this->find($token);
            if ($session !== null) {
                $this->touch($token);

                return $session;
            }
        }

        return $this->createAnonymous($request);
    }

    public function authenticate(WebSession $session, UserIdentity $user, bool $remember): void
    {
        $oldHash = self::tokenHash($session->token);
        $session->token = self::token();
        $session->user = $user;
        $session->expiresAt = $this->clock->now()->add(new DateInterval($remember ? 'P30D' : 'PT8H'));

        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE web_sessions
SET token_hash = :token_hash, user_id = :user_id, expires_at = :expires_at, last_seen_at = :now
WHERE token_hash = :old_token_hash
SQL);
        $statement->execute([
            'token_hash' => self::tokenHash($session->token),
            'user_id' => $user->id,
            'expires_at' => $session->expiresAt->format(DATE_ATOM),
            'now' => $this->clock->now()->format(DATE_ATOM),
            'old_token_hash' => $oldHash,
        ]);
    }

    public function invalidate(WebSession $session): void
    {
        $statement = $this->connection()->prepare('DELETE FROM web_sessions WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => self::tokenHash($session->token)]);
        $session->invalidated = true;
        $session->user = null;
    }

    public function verifyCsrf(WebSession $session, string $action, string $provided): void
    {
        if ($provided === '' || !hash_equals($session->csrfToken($action), $provided)) {
            throw new InvalidCsrfToken('Token CSRF non valido.');
        }
    }

    public function attachCookie(Response $response, WebSession $session): void
    {
        if ($session->invalidated) {
            $response->headers->clearCookie(self::COOKIE, '/', null, $this->secureCookie, true, 'lax');

            return;
        }

        $response->headers->setCookie(new Cookie(
            name: self::COOKIE,
            value: $session->token,
            expire: $session->expiresAt,
            path: '/',
            secure: $this->secureCookie,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));
    }

    private function find(string $token): ?WebSession
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT session.expires_at,
       users.id AS user_id, users.email, users.display_name, users.role
FROM web_sessions AS session
LEFT JOIN app_users AS users
  ON users.id = session.user_id AND users.status = 'active'
WHERE session.token_hash = :token_hash AND session.expires_at > :now
LIMIT 1
SQL);
        $statement->execute([
            'token_hash' => self::tokenHash($token),
            'now' => $this->clock->now()->format(DATE_ATOM),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $user = $row['user_id'] === null
            ? null
            : new UserIdentity(
                (string) $row['user_id'],
                (string) $row['email'],
                (string) $row['display_name'],
                (string) $row['role'],
            );

        return new WebSession($token, $user, new DateTimeImmutable((string) $row['expires_at']));
    }

    private function createAnonymous(Request $request): WebSession
    {
        $now = $this->clock->now();
        $session = new WebSession(self::token(), null, $now->add(new DateInterval('PT30M')));
        $statement = $this->connection()->prepare(<<<'SQL'
INSERT INTO web_sessions (
    token_hash, user_id, expires_at, last_seen_at, user_agent_hash, ip_address_hash, created_at
) VALUES (
    :token_hash, NULL, :expires_at, :last_seen_at, :user_agent_hash, :ip_address_hash, :created_at
)
SQL);
        $statement->execute([
            'token_hash' => self::tokenHash($session->token),
            'expires_at' => $session->expiresAt->format(DATE_ATOM),
            'last_seen_at' => $now->format(DATE_ATOM),
            'user_agent_hash' => hash('sha256', (string) $request->headers->get('User-Agent')),
            'ip_address_hash' => hash('sha256', (string) $request->getClientIp()),
            'created_at' => $now->format(DATE_ATOM),
        ]);

        return $session;
    }

    private function touch(string $token): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE web_sessions SET last_seen_at = :now WHERE token_hash = :token_hash
SQL);
        $statement->execute([
            'now' => $this->clock->now()->format(DATE_ATOM),
            'token_hash' => self::tokenHash($token),
        ]);
    }

    private static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
