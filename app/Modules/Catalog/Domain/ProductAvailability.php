<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

use InvalidArgumentException;

final readonly class ProductAvailability
{
    public function __construct(
        public int $spaceQuantity,
        public int $safetyStock,
    ) {
        if ($spaceQuantity < 0 || $safetyStock < 0) {
            throw new InvalidArgumentException('Disponibilità Space e scorta di sicurezza non possono essere negative.');
        }
    }

    public function sellableQuantity(): int
    {
        return max(0, $this->spaceQuantity - $this->safetyStock);
    }
}
