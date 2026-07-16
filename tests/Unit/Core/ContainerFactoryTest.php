<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Configuration\AutomationConfig;
use Hapa\Core\Configuration\ConfigurationSet;
use Hapa\Core\Configuration\DatabaseConfig;
use Hapa\Core\Configuration\IntegrationConfig;
use Hapa\Core\Configuration\ProxyConfig;
use Hapa\Core\Configuration\RedisConfig;
use Hapa\Core\Console\SystemCheckCommand;
use Hapa\Core\Console\AutomationRunCommand;
use Hapa\Composition\ContainerFactory;
use Hapa\Core\Kernel;
use PHPUnit\Framework\TestCase;

final class ContainerFactoryTest extends TestCase
{
    public function testServicesArePrivateByDefaultAndAliasesAreExplicit(): void
    {
        $container = (new ContainerFactory())->build(dirname(__DIR__, 3), $this->configuration());

        self::assertFalse($container->getDefinition(SystemClock::class)->isPublic());
        self::assertFalse($container->getDefinition(ApplicationConfig::class)->isPublic());
        self::assertSame(SystemClock::class, (string) $container->getAlias(Clock::class));
        self::assertFalse($container->getAlias(Clock::class)->isPublic());
        self::assertTrue($container->getDefinition(Kernel::class)->isPublic());
        self::assertTrue($container->getDefinition(SystemCheckCommand::class)->isPublic());
        self::assertTrue($container->getDefinition(AutomationRunCommand::class)->isPublic());
    }

    public function testTheContainerCompilesAndBuildsBothEntryPoints(): void
    {
        $container = (new ContainerFactory())->create(dirname(__DIR__, 3), $this->configuration());

        self::assertTrue($container->isCompiled());
        self::assertInstanceOf(Kernel::class, $container->get(Kernel::class));
        self::assertInstanceOf(SystemCheckCommand::class, $container->get(SystemCheckCommand::class));
        self::assertInstanceOf(AutomationRunCommand::class, $container->get(AutomationRunCommand::class));
    }

    private function configuration(): ConfigurationSet
    {
        return new ConfigurationSet(
            new ApplicationConfig('testing', false, 'http://localhost', 'UTC', 'warning'),
            new DatabaseConfig('127.0.0.1', 5432, 'hapa_test', 'hapa', '', 5),
            new RedisConfig('127.0.0.1', 6379, '', 2.0),
            new ProxyConfig([]),
            new IntegrationConfig(5.0, 30.0, 2 * 1024 * 1024),
            new AutomationConfig(50, 300, 30, 3600),
        );
    }
}
