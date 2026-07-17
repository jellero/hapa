<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Security;

use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Security\UserIdentity;
use PHPUnit\Framework\TestCase;

final class AuthorizationPolicyTest extends TestCase
{
    public function testItIsDenyByDefaultAndKeepsAdministrationSeparated(): void
    {
        $policy = new AuthorizationPolicy();
        $administrator = new UserIdentity('admin', 'admin@example.test', 'Admin', 'administrator');
        $operator = new UserIdentity('operator', 'operator@example.test', 'Operator', 'operator');
        $unknown = new UserIdentity('unknown', 'unknown@example.test', 'Unknown', 'custom-role');

        self::assertTrue($policy->allows($administrator, 'users.manage'));
        self::assertTrue($policy->allows($operator, 'orders.view'));
        self::assertFalse($policy->allows($operator, 'users.manage'));
        self::assertFalse($policy->allows($unknown, 'ui.view'));
        self::assertFalse($policy->allows($operator, 'permission.not.registered'));
    }
}
