<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use DateTimeZone;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class ApplicationConfig
{
    public string $name;
    public string $appUrl;
    public string $timezone;
    public string $logLevel;

    public function __construct(
        string $name,
        public bool $debug,
        string $appUrl,
        string $timezone,
        string $logLevel,
    ) {
        $normalizedName = strtolower(trim($name));
        if (!in_array($normalizedName, ['development', 'testing', 'production'], true)) {
            throw new HapaRuntimeException(sprintf('APP_ENV non valido: %s', $name));
        }

        $normalizedUrl = rtrim(trim($appUrl), '/');
        $urlParts = parse_url($normalizedUrl);
        if (
            filter_var($normalizedUrl, FILTER_VALIDATE_URL) === false
            || !is_array($urlParts)
            || !in_array($urlParts['scheme'] ?? null, ['http', 'https'], true)
            || !isset($urlParts['host'])
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($urlParts['query'])
            || isset($urlParts['fragment'])
        ) {
            throw new HapaRuntimeException(sprintf('APP_URL non valido: %s', $appUrl));
        }

        $normalizedTimezone = trim($timezone);
        if (!in_array($normalizedTimezone, DateTimeZone::listIdentifiers(), true)) {
            throw new HapaRuntimeException(sprintf('APP_TIMEZONE non valido: %s', $timezone));
        }

        $normalizedLogLevel = strtolower(trim($logLevel));
        if (!in_array($normalizedLogLevel, [
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
        ], true)) {
            throw new HapaRuntimeException(sprintf('LOG_LEVEL non valido: %s', $logLevel));
        }

        if ($normalizedName === 'production' && $debug) {
            throw new HapaRuntimeException('APP_DEBUG deve essere false in produzione.');
        }

        if ($normalizedName === 'production' && !str_starts_with($normalizedUrl, 'https://')) {
            throw new HapaRuntimeException('APP_URL deve usare HTTPS in produzione.');
        }

        $this->name = $normalizedName;
        $this->appUrl = $normalizedUrl;
        $this->timezone = $normalizedTimezone;
        $this->logLevel = $normalizedLogLevel;
    }

    public function isProduction(): bool
    {
        return $this->name === 'production';
    }
}
