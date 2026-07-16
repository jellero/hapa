<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

final readonly class OrderLineDecision
{
    public function __construct(
        public int $lineNumber,
        public int $quantityToShip,
        public int $quantityToCancel,
    ) {
        if ($lineNumber < 1) {
            throw new OrderDomainException('Il numero riga della decisione deve essere positivo.');
        }

        if ($quantityToShip < 0 || $quantityToCancel < 0) {
            throw new OrderDomainException('Le quantità della decisione non possono essere negative.');
        }
    }
}
