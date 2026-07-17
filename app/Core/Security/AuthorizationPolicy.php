<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

final class AuthorizationPolicy
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'administrator' => ['*'],
        'operator' => [
            'ui.view',
            'customers.view',
            'orders.view',
            'catalog.view',
            'shipping.view',
            'integrations.view',
            'profile.view',
        ],
        'viewer' => [
            'ui.view',
            'customers.view',
            'orders.view',
            'catalog.view',
            'shipping.view',
            'integrations.view',
            'profile.view',
        ],
    ];

    public function allows(UserIdentity $user, string $permission): bool
    {
        $permissions = self::ROLE_PERMISSIONS[$user->role] ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
