<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final class EnvironmentReader
{
    public static function value(string $name, ?string $default = null): string
    {
        $environmentValue = $_ENV[$name] ?? null;
        if (is_string($environmentValue) && $environmentValue !== '') {
            return $environmentValue;
        }

        $processValue = getenv($name);
        if (is_string($processValue) && $processValue !== '') {
            return $processValue;
        }

        if ($default === null) {
            throw new RuntimeException(sprintf('Variabile ambiente obbligatoria assente: %s', $name));
        }

        return $default;
    }

    public static function secret(string $name, ?string $default = null): string
    {
        $file = self::value($name . '_FILE', '');
        if ($file === '') {
            return self::value($name, $default);
        }

        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException(sprintf('Secret file non leggibile per %s.', $name));
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Impossibile leggere il secret file per %s.', $name));
        }

        $secret = rtrim($contents, "\r\n");
        if ($secret !== '') {
            return $secret;
        }

        if ($default === null) {
            throw new RuntimeException(sprintf('Secret file vuoto per %s.', $name));
        }

        return $default;
    }
}
