<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use DateTimeImmutable;
use Hapa\Modules\Catalog\Contract\Money;
use InvalidArgumentException;

final readonly class SpaceCatalogItem
{
    public function __construct(
        public string $sku,
        public string $spaceItemId,
        public Money $price,
        public int $availableQuantity,
        public string $sourceVersion,
        public DateTimeImmutable $observedAt,
        public ?string $ean = null,
        public ?string $name = null,
        public ?string $description = null,
    ) {
        self::validateIdentity($sku, $spaceItemId);
        self::validateAvailability($availableQuantity);
        self::validateSourceVersion($sourceVersion);
        self::validateMetadata($ean, $name, $description);
    }

    private static function validateIdentity(string $sku, string $spaceItemId): void
    {
        if (!self::isRequiredIdentifier($sku) || !self::isRequiredIdentifier($spaceItemId)) {
            throw new InvalidArgumentException('SKU e identificativo articolo Space sono obbligatori.');
        }
    }

    private static function validateAvailability(int $availableQuantity): void
    {
        if ($availableQuantity < 0) {
            throw new InvalidArgumentException('La disponibilità articolo Space non può essere negativa.');
        }
    }

    private static function validateSourceVersion(string $sourceVersion): void
    {
        if (trim($sourceVersion) !== $sourceVersion || $sourceVersion === '' || strlen($sourceVersion) > 160) {
            throw new InvalidArgumentException('La versione sorgente Space è obbligatoria.');
        }
    }

    private static function validateMetadata(?string $ean, ?string $name, ?string $description): void
    {
        foreach (['EAN' => $ean, 'nome' => $name, 'descrizione' => $description] as $field => $value) {
            if ($value !== null && (trim($value) !== $value || $value === '')) {
                throw new InvalidArgumentException(sprintf('Il campo %s Space non è valido.', $field));
            }
        }

        if ($ean !== null && strlen($ean) > 32) {
            throw new InvalidArgumentException('EAN Space troppo lungo.');
        }
        if ($name !== null && strlen($name) > 255) {
            throw new InvalidArgumentException('Nome prodotto Space troppo lungo.');
        }
        if ($description !== null && strlen($description) > 10000) {
            throw new InvalidArgumentException('Descrizione prodotto Space troppo lunga.');
        }
    }

    private static function isRequiredIdentifier(string $value): bool
    {
        return trim($value) === $value && $value !== '' && strlen($value) <= 160;
    }
}
