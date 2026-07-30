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

    public function testItAcceptsTheDedicatedSpeceFeedConfiguration(): void
    {
        $configuration = (new IntegrationAccountConfiguration())->validate(
            'space',
            'space-primary',
            'Space principale',
            'production',
            null,
            ['catalog.read'],
            [
                'base_url' => 'https://admin.space1999.com',
                'health_path' => '/apih/index.php?action=help',
                'catalog_incremental_path' => '/apih/index.php',
                'catalog_incremental_action' => 'spece',
                'catalog_page_size' => 1000,
                'catalog_field_mapping' => [
                    'idspace' => 'id_album',
                    'idspacefull' => 'id_space_full',
                    'barcode' => 'ean',
                    'price' => 'prezzo_vendita',
                    'stock' => 'onstock',
                    'delitime' => 'giorni_consegna',
                ],
            ],
        );

        self::assertSame('spece', $configuration['settings']['catalog_incremental_action']);
        self::assertSame('id_album', $configuration['settings']['catalog_field_mapping']['idspace']);
        self::assertSame('prezzo_vendita', $configuration['settings']['catalog_field_mapping']['price']);
        self::assertSame('onstock', $configuration['settings']['catalog_field_mapping']['stock']);
    }

    public function testItAcceptsTheDedicatedSpaceSupplierConfiguration(): void
    {
        $configuration = (new IntegrationAccountConfiguration())->validate(
            'space',
            'space-suppliers',
            'Elenco fornitori Space',
            'production',
            null,
            ['suppliers.read'],
            [
                'base_url' => 'https://admin.space1999.com',
                'supplier_api_path' => '/apie/index.php',
                'supplier_page_size' => 1000,
                'maximum_supplier_pages_per_run' => 25,
                'poll_interval_seconds' => 3600,
            ],
        );

        self::assertSame(['suppliers.read'], $configuration['capabilities']);
        self::assertSame('/apie/index.php', $configuration['settings']['supplier_api_path']);
        self::assertSame(3600, $configuration['settings']['poll_interval_seconds']);
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
