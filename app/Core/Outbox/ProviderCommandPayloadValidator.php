<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use InvalidArgumentException;

final class ProviderCommandPayloadValidator
{
    /** @param array<string, mixed> $payload */
    public function validate(string $eventType, array $payload): void
    {
        self::string($payload, 'integration_account_code');
        self::positiveInteger($payload, 'configuration_version');
        self::string($payload, 'idempotency_key');

        match ($eventType) {
            'marketplace.product.upsert.requested' => $this->product($payload),
            'marketplace.offer.publish.requested' => $this->offer($payload),
            'space.purchase_order.submit.requested' => $this->purchaseOrder($payload),
            'shipping.shipment.create.requested' => $this->shipment($payload),
            'shipping.shipment.close.requested' => $this->shipmentClose($payload),
            'shipping.label.retrieve.requested' => $this->label($payload),
            'marketplace.fulfilment.publish.requested' => $this->fulfilment($payload),
            default => throw new InvalidArgumentException(sprintf('Comando provider non supportato: %s', $eventType)),
        };
    }

    /** @param array<string, mixed> $payload */
    private function product(array $payload): void
    {
        self::connector($payload);
        self::string($payload, 'sku');
        self::positiveInteger($payload, 'product_version');
        self::object($payload, 'fields');
    }

    /** @param array<string, mixed> $payload */
    private function offer(array $payload): void
    {
        self::connector($payload);
        self::string($payload, 'sku');
        self::positiveInteger($payload, 'offer_version');
        self::nonNegativeInteger($payload, 'price_minor');
        self::nonNegativeInteger($payload, 'quantity');
        if (preg_match('/^[A-Z]{3}$/D', self::string($payload, 'currency')) !== 1) {
            throw new InvalidArgumentException('Valuta offerta non valida.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function purchaseOrder(array $payload): void
    {
        self::string($payload, 'purchase_order_id');
        self::positiveInteger($payload, 'purchase_order_version');
        $lines = self::list($payload, 'lines');
        if ($lines === []) {
            throw new InvalidArgumentException('L’acquisto Space deve contenere almeno una riga.');
        }
        foreach ($lines as $line) {
            if (!is_array($line) || array_is_list($line)) {
                throw new InvalidArgumentException('Riga acquisto Space non valida.');
            }
            self::string($line, 'sku');
            self::positiveInteger($line, 'quantity');
            if (array_key_exists('expected_unit_cost_minor', $line)) {
                self::nonNegativeInteger($line, 'expected_unit_cost_minor');
            }
            if (array_key_exists('currency', $line)
                && preg_match('/^[A-Z]{3}$/D', self::string($line, 'currency')) !== 1) {
                throw new InvalidArgumentException('Valuta riga acquisto Space non valida.');
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function shipment(array $payload): void
    {
        self::string($payload, 'shipment_id');
        self::positiveInteger($payload, 'shipment_version');
        self::provider($payload, 'carrier');
        self::object($payload, 'recipient');
        $packages = self::list($payload, 'packages');
        if ($packages === []) {
            throw new InvalidArgumentException('La spedizione deve contenere almeno un collo.');
        }
        foreach ($packages as $package) {
            if (!is_array($package)) {
                throw new InvalidArgumentException('Collo spedizione non valido.');
            }
            self::string($package, 'package_id');
            self::positiveInteger($package, 'weight_grams');
        }
    }

    /** @param array<string, mixed> $payload */
    private function shipmentClose(array $payload): void
    {
        self::string($payload, 'shipment_id');
        self::positiveInteger($payload, 'shipment_version');
        self::provider($payload, 'provider');
        self::string($payload, 'tracking_number');
    }

    /** @param array<string, mixed> $payload */
    private function label(array $payload): void
    {
        self::string($payload, 'shipment_id');
        self::string($payload, 'package_id');
        self::provider($payload, 'provider');
        if (!in_array(self::string($payload, 'format'), ['pdf', 'zpl'], true)) {
            throw new InvalidArgumentException('Formato etichetta non supportato.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function fulfilment(array $payload): void
    {
        self::connector($payload);
        self::string($payload, 'provider_order_id');
        self::positiveInteger($payload, 'order_version');
        if (!in_array(self::string($payload, 'requested_status'), ['standby', 'accepted', 'sent', 'cancelled'], true)) {
            throw new InvalidArgumentException('Stato fulfilment non supportato.');
        }
        $tracking = $payload['tracking_number'] ?? null;
        $courier = $payload['courier_code'] ?? null;
        if (($tracking === null) !== ($courier === null)) {
            throw new InvalidArgumentException('Tracking e corriere devono essere valorizzati insieme.');
        }
        if ($tracking !== null) {
            self::string($payload, 'tracking_number');
            self::string($payload, 'courier_code');
        }
    }

    /** @param array<string, mixed> $payload */
    private static function connector(array $payload): void
    {
        if (self::string($payload, 'connector') !== 'sellrapido') {
            throw new InvalidArgumentException('Connettore marketplace non abilitato.');
        }
    }

    /** @param array<string, mixed> $payload */
    private static function provider(array $payload, string $key): void
    {
        if (!in_array(self::string($payload, $key), ['gls', 'brt'], true)) {
            throw new InvalidArgumentException('Provider di spedizione non abilitato.');
        }
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > 500) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
        }

        return trim($value);
    }

    /** @param array<string, mixed> $payload */
    private static function positiveInteger(array $payload, string $key): int
    {
        $value = self::nonNegativeInteger($payload, $key);
        if ($value < 1) {
            throw new InvalidArgumentException(sprintf('Campo %s deve essere positivo.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nonNegativeInteger(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function object(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Campo %s deve essere un oggetto.', $key));
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
            throw new InvalidArgumentException(sprintf('Campo %s deve essere una lista.', $key));
        }

        return $value;
    }
}
