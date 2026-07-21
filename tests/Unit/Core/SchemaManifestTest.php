<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Database\SchemaManifest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SchemaManifestTest extends TestCase
{
    public function testItLoadsTheVersionedSchemaManifest(): void
    {
        $manifest = SchemaManifest::load(dirname(__DIR__, 3) . '/config/schema.php');

        self::assertSame(20260721170000, $manifest->minimumVersion);
    }

    public function testItRejectsAMissingManifest(): void
    {
        $this->expectException(RuntimeException::class);

        SchemaManifest::load(dirname(__DIR__, 3) . '/config/missing-schema.php');
    }
}
