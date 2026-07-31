<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use DateTimeImmutable;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;

final readonly class SpaceCatalogObservation
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        public string $messageId,
        public string $correlationId,
        public string $externalItemId,
        public string $supplierSku,
        public ?string $ean,
        public ?string $name,
        public ?string $description,
        public int $purchaseCostMinor,
        public string $currency,
        public int $availableQuantity,
        public string $sourceVersion,
        public DateTimeImmutable $observedAt,
        /** @var array<string, mixed> */
        public array $attributes,
        public array $payload,
    ) {
    }

    public static function fromEnvelope(MessageEnvelope $message): self
    {
        if ($message->eventType !== 'space.catalog.item.observed' || $message->schemaVersion !== 1) {
            throw new InvalidArgumentException('Contratto osservazione catalogo Space non supportato.');
        }

        $payload = $message->payload;
        if (self::requiredString($payload, 'supplier') !== 'space') {
            throw new InvalidArgumentException('Il supplier dell’osservazione deve essere Space.');
        }

        $cost = self::integer($payload, 'purchase_cost_minor');
        $quantity = self::integer($payload, 'available_quantity');
        if ($cost < 0 || $quantity < 0) {
            throw new InvalidArgumentException('Costo e disponibilità Space non possono essere negativi.');
        }

        $currency = self::requiredString($payload, 'currency');
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('Valuta osservazione Space non valida.');
        }

        return new self(
            $message->messageId,
            $message->correlationId,
            self::requiredString($payload, 'external_item_id', 160),
            self::requiredString($payload, 'supplier_sku', 160),
            self::nullableString($payload, 'ean', 32),
            self::nullableString($payload, 'name', 255),
            self::nullableString($payload, 'description'),
            $cost,
            $currency,
            $quantity,
            self::requiredString($payload, 'source_version', 200),
            new DateTimeImmutable(self::requiredString($payload, 'observed_at')),
            self::attributes($payload),
            $payload,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function attributes(array $payload): array
    {
        $attributes = $payload['attributes'] ?? [];
        if (!is_array($attributes) || ($attributes !== [] && array_is_list($attributes))) {
            throw new InvalidArgumentException('Attributi feed Space non validi.');
        }

        return $attributes;
    }

    /** @param array<string, mixed> $payload */
    private static function requiredString(array $payload, string $key, int $maximumLength = 200): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) !== $value || $value === '' || strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(self::invalidStringMessage($key, $value, $maximumLength, true));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nullableString(array $payload, string $key, int $maximumLength = 10000): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || trim($value) !== $value || $value === '' || strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(self::invalidStringMessage($key, $value, $maximumLength, false));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
        }

        return $value;
    }

    private static function invalidStringMessage(
        string $key,
        mixed $value,
        int $maximumLength,
        bool $required,
    ): string {
        $prefix = sprintf('Campo %s %snon valido: ', $key, $required ? 'mancante o ' : '');
        if (!is_string($value)) {
            return $prefix . sprintf('atteso testo, ricevuto %s.', get_debug_type($value));
        }
        if ($value === '') {
            return $prefix . 'il testo è vuoto.';
        }
        if (trim($value) !== $value) {
            return $prefix . 'sono presenti spazi iniziali o finali.';
        }

        return $prefix . sprintf(
            'lunghezza %d byte (%d caratteri), massimo consentito %d byte.',
            strlen($value),
            mb_strlen($value),
            $maximumLength,
        );
    }
}
