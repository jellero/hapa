<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final readonly class AutomationAdminConfig
{
    public function __construct(
        public string $baseUrl,
        public string $accessToken,
        public float $timeoutSeconds,
        bool $production,
    ) {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('AUTOMATION_ADMIN_API_URL non valido.');
        }
        if ($production && ($parts['scheme'] ?? null) !== 'https') {
            throw new RuntimeException('AUTOMATION_ADMIN_API_URL deve usare HTTPS in produzione.');
        }
        if (strlen($accessToken) < 32) {
            throw new RuntimeException('AUTOMATION_ADMIN_API_TOKEN deve contenere almeno 32 caratteri.');
        }
        if ($timeoutSeconds <= 0 || $timeoutSeconds > 30) {
            throw new RuntimeException('AUTOMATION_ADMIN_API_TIMEOUT non valido.');
        }
    }
}
