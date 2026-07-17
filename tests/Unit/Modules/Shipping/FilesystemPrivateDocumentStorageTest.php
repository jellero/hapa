<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Shipping;

use Hapa\Modules\Shipping\Infrastructure\FilesystemPrivateDocumentStorage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FilesystemPrivateDocumentStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/hapa-private-documents-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function testItStoresReadsVerifiesAndDeletesAPrivateLabel(): void
    {
        $storage = new FilesystemPrivateDocumentStorage($this->directory, 1024);
        $stored = $storage->store('shipment_label', 'pdf', '%PDF-test');

        self::assertSame('PDF', $stored->format);
        self::assertSame(9, $stored->bytes);
        self::assertSame('%PDF-test', $storage->read($stored->reference, $stored->checksumSha256));

        $storage->delete($stored->reference);
        self::assertFileDoesNotExist($this->directory . '/' . $stored->reference);
    }

    public function testItRejectsTraversalAndUnsupportedFormats(): void
    {
        $storage = new FilesystemPrivateDocumentStorage($this->directory);
        $this->expectException(InvalidArgumentException::class);
        $storage->store('../secrets', 'xml', 'secret');
    }
}
