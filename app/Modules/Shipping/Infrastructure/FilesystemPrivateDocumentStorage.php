<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Infrastructure;

use Hapa\Modules\Shipping\Contract\PrivateDocumentStorage;
use Hapa\Modules\Shipping\Contract\StoredDocument;
use InvalidArgumentException;
use RuntimeException;

final readonly class FilesystemPrivateDocumentStorage implements PrivateDocumentStorage
{
    private string $root;

    public function __construct(string $root, private int $maximumBytes = 10_485_760)
    {
        if ($maximumBytes < 1) {
            throw new InvalidArgumentException('Dimensione massima documento non valida.');
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new RuntimeException('Impossibile creare lo storage documenti privato.');
        }
        $resolved = realpath($root);
        if ($resolved === false) {
            throw new RuntimeException('Impossibile risolvere lo storage documenti privato.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function store(string $scope, string $format, string $content): StoredDocument
    {
        $scope = strtolower(trim($scope));
        $format = strtolower(trim($format));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/D', $scope) !== 1) {
            throw new InvalidArgumentException('Ambito documento non valido.');
        }
        if (!in_array($format, ['pdf', 'zpl', 'png'], true)) {
            throw new InvalidArgumentException('Formato documento non consentito.');
        }
        $bytes = strlen($content);
        if ($bytes < 1 || $bytes > $this->maximumBytes) {
            throw new InvalidArgumentException('Dimensione documento non consentita.');
        }

        $relativeDirectory = sprintf('%s/%s', $scope, gmdate('Y/m'));
        $directory = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossibile creare la directory documento.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $format;
        $reference = $relativeDirectory . '/' . $name;
        $destination = $directory . DIRECTORY_SEPARATOR . $name;
        $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
        $written = file_put_contents($temporary, $content, LOCK_EX);
        if ($written !== $bytes) {
            @unlink($temporary);
            throw new RuntimeException('Scrittura documento incompleta.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Pubblicazione atomica del documento fallita.');
        }

        return new StoredDocument($reference, hash('sha256', $content), $bytes, strtoupper($format));
    }

    public function read(string $reference, string $expectedChecksum): string
    {
        $path = $this->resolve($reference);
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('Documento privato non leggibile.');
        }
        if (preg_match('/^[0-9a-f]{64}$/D', $expectedChecksum) !== 1
            || !hash_equals($expectedChecksum, hash('sha256', $content))) {
            throw new RuntimeException('Checksum documento non valido.');
        }

        return $content;
    }

    public function delete(string $reference): void
    {
        $path = $this->resolve($reference);
        if (!unlink($path)) {
            throw new RuntimeException('Impossibile eliminare il documento privato.');
        }
    }

    private function resolve(string $reference): string
    {
        if (preg_match('#^[a-z0-9][a-z0-9_-]{1,63}/[0-9]{4}/[0-9]{2}/[0-9a-f]{32}\.(pdf|zpl|png)$#D', $reference) !== 1) {
            throw new InvalidArgumentException('Riferimento documento non valido.');
        }
        $candidate = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference);
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR) || !is_file($resolved)) {
            throw new RuntimeException('Documento privato non trovato.');
        }

        return $resolved;
    }
}
