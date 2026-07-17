<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

use DateTimeImmutable;

final class WebSession
{
    public bool $invalidated = false;

    public function __construct(
        public string $token,
        public ?UserIdentity $user,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    public function csrfToken(string $action): string
    {
        return hash_hmac('sha256', $action, $this->token);
    }
}
