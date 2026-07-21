<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use Hapa\Core\Exception\HapaRuntimeException;

final readonly class SchemaManifest
{
    private function __construct(public int $minimumVersion)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new HapaRuntimeException(sprintf('Manifest schema non trovato: %s', $file));
        }

        // The manifest returns data and may be loaded repeatedly; require_once
        // would return true instead of the array after its first evaluation.
        $manifest = require $file; // NOSONAR
        if (!is_array($manifest)) {
            throw new HapaRuntimeException('Il manifest schema deve restituire un array.');
        }

        $minimumVersion = $manifest['minimum_version'] ?? null;
        if (!is_int($minimumVersion) || $minimumVersion < 1) {
            throw new HapaRuntimeException('minimum_version del manifest schema non è valido.');
        }

        return new self($minimumVersion);
    }
}
