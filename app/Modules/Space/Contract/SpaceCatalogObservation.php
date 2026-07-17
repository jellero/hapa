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
            $payload,
        );
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

    /** @param array<string, mixed> $payload */
    private static function nullableString(array $payload, string $key, int $maximumLength = 10000): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || trim($value) !== $value || $value === '' || strlen($value) > $maximumLength) {
            throw new InvalidArgumentException(sprintf('Campo %s non valido.', $key));
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
}
