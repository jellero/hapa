<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

use Hapa\Core\Clock\Clock;

final readonly class CredentialAuthenticator
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$R29RVE9lR2pIWDVHSmtqOQ$KHFULCSHk20nSDn1QRnJblX4HuHpTqdjKDlMXe0uQhs';

    public function __construct(
        private UserRepository $users,
        private Clock $clock,
    ) {
    }

    public function authenticate(string $email, string $password): ?UserIdentity
    {
        $record = $this->users->findActiveByEmail($email, $this->clock->now());
        $hash = $record['password_hash'] ?? self::DUMMY_HASH;
        if (!password_verify($password, $hash) || $record === null) {
            $this->users->recordFailedLogin($email, $this->clock->now());

            return null;
        }

        $user = $record['identity'];
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $rehash = password_hash($password, PASSWORD_ARGON2ID);
            $this->users->updatePasswordHash($user, $rehash, $this->clock->now());
        }

        $this->users->recordSuccessfulLogin($user, $this->clock->now());

        return $user;
    }
}
