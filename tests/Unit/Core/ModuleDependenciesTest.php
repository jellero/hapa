<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class ModuleDependenciesTest extends TestCase
{
    public function testCarrierModulesDependOnlyOnTheSharedShippingContract(): void
    {
        /** @var array<string, list<string>> $dependencies */
        $dependencies = require dirname(__DIR__, 3) . '/config/module-dependencies.php';

        self::assertSame(['Shipping'], $dependencies['Gls']);
        self::assertSame(['Shipping'], $dependencies['Brt']);
        self::assertSame([], $dependencies['Shipping']);
    }
}
