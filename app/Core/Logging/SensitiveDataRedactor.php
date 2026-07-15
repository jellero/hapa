<?php

declare(strict_types=1);

namespace Hapa\Core\Logging;

final class SensitiveDataRedactor
{
    private const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const SENSITIVE_KEY_PATTERNS = [
        'authorization',
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'certificate',
        'fiscal_code',
        'tax_code',
        'document_number',
    ];

    /**
     * @param array<string|int, mixed> $data
     * @return array<string|int, mixed>
     */
    public function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
