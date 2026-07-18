<?php

declare(strict_types=1);

namespace Hapa\Core\Integration;

use InvalidArgumentException;

final class ProviderSecretFields
{
    /** @var array<string, array<string, string>> */
    private const FIELDS = [
        'sellrapido' => ['username' => 'Username API', 'password' => 'Password API'],
        'space' => ['api_key' => 'Chiave API', 'username' => 'Username', 'password' => 'Password'],
        'gls' => ['password' => 'Password cliente GLS'],
        'brt' => ['api_key' => 'Chiave API', 'username' => 'Username', 'password' => 'Password'],
        'amazon' => [
            'lwa_client_id' => 'LWA Client ID', 'lwa_client_secret' => 'LWA Client Secret',
            'lwa_refresh_token' => 'LWA Refresh Token', 'aws_access_key_id' => 'AWS Access Key ID',
            'aws_secret_access_key' => 'AWS Secret Access Key',
        ],
        'temu' => ['app_key' => 'App Key', 'app_secret' => 'App Secret', 'access_token' => 'Access Token'],
    ];

    /** @return array<string, string> */
    public function forProvider(string $provider): array
    {
        return self::FIELDS[strtolower($provider)] ?? [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function submitted(string $provider, array $input): array
    {
        $allowed = $this->forProvider($provider);
        if ($allowed === []) {
            throw new InvalidArgumentException('Provider non supportato.');
        }
        $values = [];
        foreach ($allowed as $name => $_label) {
            $value = $input[$name] ?? null;
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                if (strlen($value) > 8192 || str_contains($value, "\0")) {
                    throw new InvalidArgumentException('Valore credenziale non valido.');
                }
                $values[$name] = $value;
            }
        }
        if ($values === []) {
            throw new InvalidArgumentException('Inserire almeno una credenziale da sostituire.');
        }

        return $values;
    }
}
