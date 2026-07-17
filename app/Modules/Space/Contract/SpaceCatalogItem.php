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
        if (
            trim($sku) !== $sku || $sku === '' || strlen($sku) > 160
            || trim($spaceItemId) !== $spaceItemId || $spaceItemId === '' || strlen($spaceItemId) > 160
        ) {
            throw new InvalidArgumentException('SKU e identificativo articolo Space sono obbligatori.');
        }

        if ($availableQuantity < 0) {
            throw new InvalidArgumentException('La disponibilità articolo Space non può essere negativa.');
        }

        if (trim($sourceVersion) !== $sourceVersion || $sourceVersion === '' || strlen($sourceVersion) > 160) {
            throw new InvalidArgumentException('La versione sorgente Space è obbligatoria.');
        }

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
}
