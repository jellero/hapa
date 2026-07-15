<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Health\ReadinessCheck;
use PHPUnit\Framework\TestCase;

final class ReadinessCheckTest extends TestCase
{
    public function testDatabaseAndRedisAreReadyAfterMigrations(): void
    {
        $result = (new ReadinessCheck(new ConnectionFactory()))->check();

        self::assertTrue($result['components']['database']);
        self::assertTrue($result['components']['redis']);
        self::assertTrue($result['ready']);
    }
}
