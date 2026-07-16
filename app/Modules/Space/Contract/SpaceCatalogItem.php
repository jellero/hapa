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
    }
}
