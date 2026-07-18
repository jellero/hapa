<?php

declare(strict_types=1);

namespace Hapa\Core\Integration;

use InvalidArgumentException;

final class IntegrationAccountConfiguration
{
    /** @var array<string, list<string>> */
    private const CAPABILITIES = [
        'sellrapido' => ['products.read', 'products.write', 'orders.read', 'orders.status.write'],
        'space' => ['catalog.read', 'purchase_orders.write', 'purchase_orders.read'],
        'gls' => ['shipping.create', 'shipping.close', 'shipping.reconcile', 'labels.read'],
        'brt' => ['shipping.create', 'shipping.close', 'shipping.reconcile', 'labels.read'],
        'amazon' => ['products.write', 'orders.read', 'orders.status.write'],
        'temu' => ['products.write', 'orders.read', 'orders.status.write'],
    ];

    /** @var array<string, list<string>> */
    private const SETTINGS = [
        'sellrapido' => [
            'base_url', 'account', 'catalog_id', 'catalog_uuid', 'downstream_channel',
            'order_states', 'poll_interval_seconds', 'overlap_seconds', 'page_size',
            'batch_size', 'catalog_mode', 'fields_lock', 'courier_mapping',
            'orders_path', 'modified_parameter', 'offset_parameter', 'limit_parameter',
            'status_parameter', 'maximum_pages_per_run', 'initial_lookback_days',
            'offer_path', 'offer_method', 'offer_min_interval_seconds', 'pilot_skus',
            'request_timeout_seconds', 'maximum_response_bytes',
        ],
        'space' => [
            'base_url', 'health_path', 'catalog_items_path', 'purchase_orders_path', 'credential_header',
            'request_timeout_seconds', 'maximum_response_bytes', 'timeout_seconds',
            'poll_interval_seconds', 'catalog_page_size', 'maximum_catalog_pages_per_run',
            'overlap_seconds', 'state_mapping_version',
        ],
        'gls' => [
            'endpoint', 'wsdl_url', 'branch_code', 'customer_code', 'contract_code',
            'aggregation_rule', 'label_format', 'close_strategy', 'timeout_seconds',
        ],
        'brt' => ['endpoint', 'customer_code', 'contract_code', 'label_format', 'timeout_seconds'],
        'amazon' => ['region', 'marketplace_id', 'poll_interval_seconds'],
        'temu' => ['base_url', 'region', 'poll_interval_seconds'],
    ];

    /**
     * @param list<mixed> $capabilities
     * @param array<string, mixed> $settings
     * @return array{
     *   provider: string, code: string, display_name: string, environment: string,
     *   description: string|null, capabilities: list<string>, settings: array<string, mixed>
     * }
     */
    public function validate(
        string $provider,
        string $code,
        string $displayName,
        string $environment,
        ?string $description,
        array $capabilities,
        array $settings,
    ): array {
        $provider = strtolower(trim($provider));
        $code = strtolower(trim($code));
        $displayName = trim($displayName);
        $environment = strtolower(trim($environment));
        $description = $description === null || trim($description) === '' ? null : trim($description);
        if (!isset(self::CAPABILITIES[$provider])) {
            throw new InvalidArgumentException('Provider non supportato.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9_-]{2,95}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Codice account non valido.');
        }
        if ($displayName === '' || mb_strlen($displayName) > 160) {
            throw new InvalidArgumentException('Nome account non valido.');
        }
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('Ambiente provider non valido.');
        }
        if ($description !== null && mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('Descrizione account troppo lunga.');
        }

        $normalizedCapabilities = [];
        foreach ($capabilities as $capability) {
            if (!is_string($capability) || !in_array($capability, self::CAPABILITIES[$provider], true)) {
                throw new InvalidArgumentException('Capacità provider non consentita.');
            }
            $normalizedCapabilities[] = $capability;
        }
        $normalizedCapabilities = array_values(array_unique($normalizedCapabilities));
        sort($normalizedCapabilities);

        foreach ($settings as $key => $value) {
            if (!is_string($key) || !in_array($key, self::SETTINGS[$provider], true)) {
                throw new InvalidArgumentException(sprintf('Impostazione %s non consentita per %s.', (string) $key, $provider));
            }
            if ($this->containsSensitiveKey([$key => $value])) {
                throw new InvalidArgumentException('Le credenziali non possono essere salvate in HAPA.');
            }
            if (in_array($key, ['base_url', 'endpoint', 'wsdl_url'], true)) {
                $this->validateUrl($value, $environment);
            }
        }
        ksort($settings);

        return [
            'provider' => $provider,
            'code' => $code,
            'display_name' => $displayName,
            'environment' => $environment,
            'description' => $description,
            'capabilities' => $normalizedCapabilities,
            'settings' => $settings,
        ];
    }

    /** @return array<string, list<string>> */
    public function availableCapabilities(): array
    {
        return self::CAPABILITIES;
    }

    private function validateUrl(mixed $value, string $environment): void
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Endpoint provider non valido.');
        }
        $parts = parse_url($value);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Endpoint provider non sicuro.');
        }
        if ($environment === 'production' && ($parts['scheme'] ?? null) !== 'https') {
            throw new InvalidArgumentException('Gli endpoint di produzione devono usare HTTPS.');
        }
    }

    /** @param array<string|int, mixed> $value */
    private function containsSensitiveKey(array $value): bool
    {
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/password|passwd|secret|token|api.?key|authorization|cookie/i', $key)) {
                return true;
            }
            if (is_array($item) && $this->containsSensitiveKey($item)) {
                return true;
            }
        }

        return false;
    }
}
