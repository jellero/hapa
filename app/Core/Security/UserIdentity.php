<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

final readonly class UserIdentity
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $role,
    ) {
    }
}
