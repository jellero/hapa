<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use DateTimeZone;
use RuntimeException;

final readonly class Environment
{
    private function __construct(
        public string $name,
        public bool $debug,
        public string $appUrl,
        public string $timezone,
    ) {
    }

    public static function load(): self
    {
        $name = strtolower(trim(self::value('APP_ENV', 'development')));
        $debug = filter_var(self::value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
        $appUrl = rtrim(self::value('APP_URL', 'http://localhost:8080'), '/');
        $timezone = self::value('APP_TIMEZONE', 'Europe/Rome');

        if (!in_array($name, ['development', 'testing', 'production'], true)) {
            throw new RuntimeException(sprintf('APP_ENV non valido: %s', $name));
        }

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException(sprintf('APP_TIMEZONE non valido: %s', $timezone));
        }

        if ($name === 'production') {
            if ($debug) {
                throw new RuntimeException('APP_DEBUG deve essere false in produzione.');
            }

            if (!str_starts_with($appUrl, 'https://')) {
                throw new RuntimeException('APP_URL deve usare HTTPS in produzione.');
            }

            self::assertProductionSecret('DB_PASSWORD');
            self::assertProductionSecret('REDIS_PASSWORD');
        }

        return new self($name, $debug, $appUrl, $timezone);
    }

    public function isProduction(): bool
    {
        return $this->name === 'production';
    }

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

        if ($file !== '') {
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

        return self::value($name, $default);
    }

    private static function assertProductionSecret(string $name): void
    {
        $value = self::secret($name);
        $normalized = strtolower($value);

        foreach (['replace_with_secret', 'change-me', 'changeme', 'local'] as $forbidden) {
            if (str_contains($normalized, $forbidden)) {
                throw new RuntimeException(sprintf('%s contiene un valore non ammesso in produzione.', $name));
            }
        }

        if (strlen($value) < 16) {
            throw new RuntimeException(sprintf('%s deve contenere almeno 16 caratteri in produzione.', $name));
        }
    }
}
