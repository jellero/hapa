<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\SchemaVersion;
use Hapa\Core\Health\ReadinessCheck;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ReadinessCheckTest extends TestCase
{
    public function testPostgresRedisAndSchemaAreReady(): void
    {
        $result = (new ReadinessCheck(
            new ConnectionFactory(),
            new NullLogger(),
            SchemaVersion::LATEST,
        ))->check();

        if (getenv('CI') !== 'true' && !$result['ready']) {
            self::markTestSkipped('Servizi integration locali non disponibili.');
        }

        self::assertTrue($result['components']['database']);
        self::assertTrue($result['components']['redis']);
        self::assertTrue($result['ready']);
    }
}
