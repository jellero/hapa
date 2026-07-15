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
        /** @var list<string> */
        public array $trustedProxies,
    ) {
    }

    public static function load(): self
    {
        $name = strtolower(trim(self::value('APP_ENV', 'development')));
        $debug = filter_var(self::value('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
        $appUrl = rtrim(self::value('APP_URL', 'http://localhost:8080'), '/');
        $timezone = self::value('APP_TIMEZONE', 'Europe/Rome');
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', self::value('TRUSTED_PROXIES', '')),
        )));

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

            if ($trustedProxies === []) {
                throw new RuntimeException('TRUSTED_PROXIES deve essere configurato in produzione.');
            }

            self::assertProductionSecret('DB_PASSWORD');
            self::assertProductionSecret('REDIS_PASSWORD');
        }

        return new self($name, $debug, $appUrl, $timezone, $trustedProxies);
    }

    public function isProduction(): bool
    {
        return $this->name === 'production';
    }

    public static function value(string $name, ?string $default = null): string
    {
        return EnvironmentReader::value($name, $default);
    }

    public static function secret(string $name, ?string $default = null): string
    {
        return EnvironmentReader::secret($name, $default);
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
