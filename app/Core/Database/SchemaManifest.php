<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use RuntimeException;

final readonly class SchemaManifest
{
    private function __construct(public int $minimumVersion)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Manifest schema non trovato: %s', $file));
        }

        $manifest = require $file;
        if (!is_array($manifest)) {
            throw new RuntimeException('Il manifest schema deve restituire un array.');
        }

        $minimumVersion = $manifest['minimum_version'] ?? null;
        if (!is_int($minimumVersion) || $minimumVersion < 1) {
            throw new RuntimeException('minimum_version del manifest schema non è valido.');
        }

        return new self($minimumVersion);
    }
}
