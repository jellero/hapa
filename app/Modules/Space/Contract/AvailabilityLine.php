<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use InvalidArgumentException;

final readonly class AvailabilityLine
{
    public function __construct(
        public string $sku,
        public int $requested,
        public int $available,
        public int $missing,
    ) {
        if (trim($sku) === '') {
            throw new InvalidArgumentException('Lo SKU è obbligatorio.');
        }

        if ($requested < 0 || $available < 0 || $missing < 0) {
            throw new InvalidArgumentException('Le quantità di disponibilità non possono essere negative.');
        }
    }
}
