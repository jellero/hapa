<?php

declare(strict_types=1);

namespace Hapa\Core\Integration;

use Hapa\Core\Configuration\AutomationAdminConfig;
use JsonException;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class AutomationSecretClient implements ProviderSecretGateway, ProviderConfigurationGateway
{
    public function __construct(private AutomationAdminConfig $configuration)
    {
    }

    /**
     * @param array<string, string> $secrets
     * @return array<string, mixed>
     */
    public function replace(string $account, string $provider, array $secrets, string $actorId, string $correlationId): array
    {
        return $this->request('PUT', $account, 'secrets', [
            'provider' => $provider,
            'secrets' => $secrets,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
        ]);
    }

    public function revoke(string $account, string $provider, string $actorId, string $correlationId): array
    {
        return $this->request('DELETE', $account, 'secrets', [
            'provider' => $provider,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
        ]);
    }

    public function status(string $account): array
    {
        return $this->request('GET', $account, 'secrets');
    }

    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    public function apply(array $account, string $actorId, string $correlationId): array
    {
        return $this->request('PUT', (string) ($account['code'] ?? ''), 'configuration', [
            'provider' => $account['provider_code'] ?? null,
            'configuration_version' => $account['configuration_version'] ?? null,
            'environment' => $account['environment'] ?? null,
            'desired_status' => $account['desired_status'] ?? null,
            'capabilities' => $account['capabilities'] ?? null,
            'settings' => $account['settings'] ?? null,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
        ]);
    }

    public function configurationStatus(string $account): array
    {
        return $this->request('GET', $account, 'configuration');
    }

    public function testConnection(string $account): array
    {
        return $this->request('POST', $account, 'connection-test');
    }

    public function importOrders(string $account): array
    {
        return $this->request('POST', $account, 'orders/import');
    }

    public function synchronizeCatalog(string $account): array
    {
        return $this->request('POST', $account, 'catalog/sync');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws JsonException
     */
    private function request(string $method, string $account, string $resource, array $payload = []): array
    {
        $options = [
            'method' => $method,
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->configuration->accessToken,
                'Cache-Control: no-store',
            ]),
            'timeout' => $this->configuration->timeoutSeconds,
            'ignore_errors' => true,
        ];
        if ($method !== 'GET') {
            $options['content'] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $context = stream_context_create(['http' => $options]);
        $url = rtrim($this->configuration->baseUrl, '/') . '/internal/v1/provider-accounts/' . rawurlencode($account) . '/' . $resource;
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || strlen($response) > 65536) {
            throw new HapaRuntimeException('Servizio credenziali Automation non disponibile.');
        }
        $status = $this->statusCode($http_response_header);
        $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new HapaRuntimeException('Risposta Automation non valida.');
        }
        if ($status < 200 || $status >= 300) {
            $message = $decoded['error'] ?? null;
            throw new HapaRuntimeException(is_string($message) ? $message : 'Operazione credenziali rifiutata da Automation.');
        }

        return $decoded;
    }

    /** @param list<string> $headers */
    private function statusCode(array $headers): int
    {
        $first = $headers[0] ?? '';
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $first, $matches) !== 1) {
            throw new HapaRuntimeException('Risposta HTTP Automation non valida.');
        }

        return (int) $matches[1];
    }
}
