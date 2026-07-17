<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Integration\IntegrationAccountConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntegrationAccountConfigurationTest extends TestCase
{
    public function testItNormalizesANonSecretSellRapidoConfiguration(): void
    {
        $configuration = (new IntegrationAccountConfiguration())->validate(
            'SellRapido',
            'sellrapido-primary',
            'SellRapido principale',
            'production',
            null,
            ['orders.read', 'products.read', 'orders.read'],
            ['base_url' => 'https://app.sellrapido.com/sr_company_ws', 'batch_size' => 1000],
        );

        self::assertSame('sellrapido', $configuration['provider']);
        self::assertSame(['orders.read', 'products.read'], $configuration['capabilities']);
    }

    /** @param array<string, mixed> $settings */
    #[DataProvider('invalidConfigurations')]
    public function testItRejectsSecretsAndUnsafeProductionEndpoints(array $settings): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new IntegrationAccountConfiguration())->validate(
            'space',
            'space-primary',
            'Space principale',
            'production',
            null,
            ['catalog.read'],
            $settings,
        );
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'secret key' => [['password' => 'must-not-be-stored']];
        yield 'nested token' => [['state_mapping_version' => ['access_token' => 'must-not-be-stored']]];
        yield 'plain HTTP in production' => [['base_url' => 'http://space.internal']];
    }
}
