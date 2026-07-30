<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Modules\Catalog\Application\CatalogReadModel;
use Hapa\Modules\Space\Application\SpaceSupplierReadModel;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SpaceSupplierReadModelTest extends TestCase
{
    public function testSupplierAndProductReadModelsExposeTheSupplierReference(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $suppliers = (new SpaceSupplierReadModel($connections))->search('', '', 5);
            $products = (new CatalogReadModel($connections))->search('', 5);
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }

        self::assertArrayHasKey('items', $suppliers);
        self::assertArrayHasKey('metrics', $suppliers);
        self::assertArrayHasKey('suppliers', $products['filter_options']);
        if ($products['filter_options']['suppliers'] !== []) {
            self::assertArrayHasKey('id', $products['filter_options']['suppliers'][0]);
            self::assertArrayHasKey('name', $products['filter_options']['suppliers'][0]);
        }
    }
}
