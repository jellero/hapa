<?php

declare(strict_types=1);

namespace Hapa\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NamespaceTest extends TestCase
{
    public function testLegacyPmsNamespaceIsAbsent(): void
    {
        $basePath = dirname(__DIR__, 2) . '/app';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname()) ?: '';
            self::assertStringNotContainsString('Pms\\', $content, $file->getPathname());
        }
    }
}
