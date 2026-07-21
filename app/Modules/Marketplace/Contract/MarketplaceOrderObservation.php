<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use DateTimeImmutable;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;
use Throwable;

final readonly class MarketplaceOrderObservation
{
    /**
     * @param array{order_minor: int, shipping_minor: int, marketplace_fee_minor: int, tax_minor: int} $totals
     * @param array{name: string, email: ?string, phone: ?string, fiscal_code: ?string, vat_number: ?string, external_customer_id: ?string} $customer
     * @param array{recipient: string, address_line1: string, address_line2: ?string, postal_code: string, city: string, province: ?string, country_code: string, phone: ?string} $shippingAddress
     * @param list<array{provider_row_id: string, transaction_id: ?string, external_product_id: ?string, sku: string, ean: ?string, title: string, quantity: int, unit_price_minor: int, total_price_minor: int, shipping_minor: int, tax_rate_basis_points: int}> $rows
     */
    private function __construct(
        public string $messageId,
        public string $correlationId,
        public string $integrationAccountCode,
        public string $providerOrderId,
        public string $externalOrderId,
        public string $marketplaceCode,
        public ?string $channelCode,
        public string $providerStatus,
        public string $sourceVersion,
        public DateTimeImmutable $orderedAt,
        public DateTimeImmutable $modifiedAt,
        public DateTimeImmutable $observedAt,
        public string $currency,
        public array $totals,
        public array $customer,
        public array $shippingAddress,
        public array $rows,
    ) {
    }

    public static function fromEnvelope(MessageEnvelope $message): self
    {
        if ($message->eventType !== 'marketplace.order.observed' || $message->schemaVersion !== 1) {
            throw new InvalidArgumentException('Contratto osservazione ordine marketplace non supportato.');
        }

        $payload = $message->payload;
        if (self::requiredString($payload, 'connector', 64) !== 'sellrapido') {
            throw new InvalidArgumentException('Il connector dell\'osservazione deve essere SellRapido.');
        }
        $status = self::requiredString($payload, 'provider_status', 32);
        if (!in_array($status, ['standby', 'accepted', 'sent', 'cancelled'], true)) {
            throw new InvalidArgumentException('Stato ordine SellRapido non supportato.');
        }
        $currency = self::requiredString($payload, 'currency', 3);
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Valuta ordine SellRapido non valida.');
        }

        $normalizedTotals = self::normalizeTotals($payload);
        $normalizedCustomer = self::normalizeCustomer($payload);
        $normalizedShipping = self::normalizeShipping($payload, $normalizedCustomer['phone']);
        $rows = self::normalizeRows($payload);
        [$orderedAt, $modifiedAt, $observedAt] = self::normalizeDates($payload);

        return new self(
            $message->messageId,
            $message->correlationId,
            self::requiredString($payload, 'integration_account_code', 96),
            self::requiredString($payload, 'provider_order_id', 160),
            self::requiredString($payload, 'external_order_id', 160),
            strtolower(self::requiredString($payload, 'marketplace_code', 96)),
            self::nullableString($payload, 'channel_code', 96),
            $status,
            self::requiredString($payload, 'source_version', 200),
            $orderedAt,
            $modifiedAt,
            $observedAt,
            $currency,
            $normalizedTotals,
            $normalizedCustomer,
            $normalizedShipping,
            $rows,
        );
    }

    /** @param array<string, mixed> $payload
     *  @return array{order_minor:int,shipping_minor:int,marketplace_fee_minor:int,tax_minor:int}
     */
    private static function normalizeTotals(array $payload): array
    {
        $totals = self::object($payload, 'totals');
        return [
            'order_minor' => self::nonNegativeInteger($totals, 'order_minor'),
            'shipping_minor' => self::nonNegativeInteger($totals, 'shipping_minor'),
            'marketplace_fee_minor' => self::nonNegativeInteger($totals, 'marketplace_fee_minor'),
            'tax_minor' => self::nonNegativeInteger($totals, 'tax_minor'),
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array{name:string,email:?string,phone:?string,fiscal_code:?string,vat_number:?string,external_customer_id:?string}
     */
    private static function normalizeCustomer(array $payload): array
    {
        $customer = self::object($payload, 'customer');
        $normalized = [
            'name' => self::requiredString($customer, 'name', 240),
            'email' => self::nullableString($customer, 'email', 254),
            'phone' => self::nullableString($customer, 'phone', 64),
            'fiscal_code' => self::nullableString($customer, 'fiscal_code', 64),
            'vat_number' => self::nullableString($customer, 'vat_number', 32),
            'external_customer_id' => self::nullableString($customer, 'external_customer_id', 160),
        ];
        if ($normalized['email'] !== null && filter_var($normalized['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email cliente SellRapido non valida.');
        }
        return $normalized;
    }

    /** @param array<string, mixed> $payload
     *  @return array{recipient:string,address_line1:string,address_line2:?string,postal_code:string,city:string,province:?string,country_code:string,phone:?string}
     */
    private static function normalizeShipping(array $payload, ?string $customerPhone): array
    {
        $shipping = self::object($payload, 'shipping_address');
        return [
            'recipient' => self::requiredStringAlias($shipping, ['recipient', 'name'], 240),
            'address_line1' => self::requiredStringAlias($shipping, ['address_line1', 'address1'], 240),
            'address_line2' => self::nullableStringAlias($shipping, ['address_line2', 'address2'], 240),
            'postal_code' => self::requiredString($shipping, 'postal_code', 32),
            'city' => self::requiredString($shipping, 'city', 160),
            'province' => self::nullableString($shipping, 'province', 120),
            'country_code' => self::countryCode(self::requiredStringAlias($shipping, ['country_code', 'country'], 3)),
            'phone' => self::nullableString($shipping, 'phone', 64) ?? $customerPhone,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{provider_row_id:string,transaction_id:?string,external_product_id:?string,sku:string,ean:?string,title:string,quantity:int,unit_price_minor:int,total_price_minor:int,shipping_minor:int,tax_rate_basis_points:int}>
     */
    private static function normalizeRows(array $payload): array
    {
        $rawRows = self::list($payload, 'rows');
        if ($rawRows === []) {
            throw new InvalidArgumentException('L\'ordine SellRapido non contiene righe.');
        }
        $rows = [];
        foreach ($rawRows as $index => $rawRow) {
            $rows[] = self::normalizeRow($rawRow, $index);
        }
        return $rows;
    }

    /** @return array{provider_row_id:string,transaction_id:?string,external_product_id:?string,sku:string,ean:?string,title:string,quantity:int,unit_price_minor:int,total_price_minor:int,shipping_minor:int,tax_rate_basis_points:int} */
    private static function normalizeRow(mixed $rawRow, int $index): array
    {
        if (!is_array($rawRow) || array_is_list($rawRow)) {
            throw new InvalidArgumentException(sprintf('Riga SellRapido %d non valida.', $index));
        }
        return [
            'provider_row_id' => self::requiredString($rawRow, 'provider_row_id', 160),
            'transaction_id' => self::nullableString($rawRow, 'transaction_id', 160),
            'external_product_id' => self::nullableString($rawRow, 'external_product_id', 160),
            'sku' => self::requiredString($rawRow, 'sku', 160),
            'ean' => self::nullableString($rawRow, 'ean', 32),
            'title' => self::requiredString($rawRow, 'title', 500),
            'quantity' => self::positiveInteger($rawRow, 'quantity'),
            'unit_price_minor' => self::nonNegativeInteger($rawRow, 'unit_price_minor'),
            'total_price_minor' => self::nonNegativeInteger($rawRow, 'total_price_minor'),
            'shipping_minor' => self::nonNegativeInteger($rawRow, 'shipping_minor'),
            'tax_rate_basis_points' => self::taxRate($rawRow, $index),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function taxRate(array $row, int $index): int
    {
        $vat = self::requiredString($row, 'vat_percent', 16);
        if (!preg_match('/^(?:0|[1-9]\d?|100)(?:\.\d{1,2})?$/D', $vat)) {
            throw new InvalidArgumentException(sprintf('IVA riga SellRapido %d non valida.', $index));
        }
        if (!str_contains($vat, '.')) {
            return (int) $vat * 100;
        }
        [$whole, $fraction] = explode('.', $vat, 2);
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{DateTimeImmutable,DateTimeImmutable,DateTimeImmutable}
     */
    private static function normalizeDates(array $payload): array
    {
        $orderedAt = self::date($payload, 'ordered_at');
        $modifiedAt = self::date($payload, 'modified_at');
        $observedAt = self::date($payload, 'observed_at');
        if ($modifiedAt < $orderedAt || $observedAt < $modifiedAt) {
            throw new InvalidArgumentException('Le date SellRapido non sono ordinate cronologicamente.');
        }
        return [$orderedAt, $modifiedAt, $observedAt];
    }

    /** @param array<string, mixed> $payload */
    private static function requiredString(array $payload, string $key, int $maximumLength = 200): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) !== $value || $value === '' || strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(sprintf('Campo %s mancante o non valido.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $aliases
     */
    private static function requiredStringAlias(array $payload, array $aliases, int $maximumLength): string
    {
        foreach ($aliases as $key) {
            if (array_key_exists($key, $payload)) {
                return self::requiredString($payload, $key, $maximumLength);
            }
        }
        throw new InvalidArgumentException(sprintf('Campo %s mancante.', implode('/', $aliases)));
    }

    /** @param array<string, mixed> $payload */
    private static function nullableString(array $payload, string $key, int $maximumLength): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || trim($value) !== $value || strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $aliases
     */
    private static function nullableStringAlias(array $payload, array $aliases, int $maximumLength): ?string
    {
        foreach ($aliases as $key) {
            if (array_key_exists($key, $payload)) {
                return self::nullableString($payload, $key, $maximumLength);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function object(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Oggetto %s non valido.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<mixed>
     */
    private static function list(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Lista %s non valida.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nonNegativeInteger(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException(sprintf('Intero %s non valido.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function positiveInteger(array $payload, string $key): int
    {
        $value = self::nonNegativeInteger($payload, $key);
        if ($value === 0) {
            throw new InvalidArgumentException(sprintf('Intero %s deve essere positivo.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function date(array $payload, string $key): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(self::requiredString($payload, $key, 64));
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(sprintf('Data %s non valida.', $key), 0, $exception);
        }
    }

    private static function countryCode(string $value): string
    {
        $code = strtoupper($value);
        $code = match ($code) {
            'ITA' => 'IT', 'DEU' => 'DE', 'FRA' => 'FR', 'ESP' => 'ES', 'PRT' => 'PT',
            'AUT' => 'AT', 'BEL' => 'BE', 'NLD' => 'NL', 'LUX' => 'LU', 'GRC' => 'GR',
            'GBR' => 'GB', 'IRL' => 'IE', 'POL' => 'PL', 'ROU' => 'RO', 'BGR' => 'BG',
            default => $code,
        };
        if (!preg_match('/^[A-Z]{2}$/D', $code)) {
            throw new InvalidArgumentException('Codice paese SellRapido non supportato.');
        }

        return $code;
    }
}
