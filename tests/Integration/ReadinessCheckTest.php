<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\SchemaManifest;
use Hapa\Core\Health\ReadinessCheck;
use PHPUnit\Framework\TestCase;

final class ReadinessCheckTest extends TestCase
{
    public function testDatabaseAndRedisAreReadyAfterMigrations(): void
    {
        $basePath = dirname(__DIR__, 2);
        $manifest = SchemaManifest::load($basePath . '/config/schema.php');
        $result = (new ReadinessCheck(new ConnectionFactory(), $manifest->minimumVersion))->check();

        self::assertTrue($result['components']['database']);
        self::assertTrue($result['components']['redis']);
        self::assertTrue($result['ready']);
    }
}
