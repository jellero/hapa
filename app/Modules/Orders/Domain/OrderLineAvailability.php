<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

final readonly class OrderLineAvailability
{
    public function __construct(
        public int $lineNumber,
        public int $quantityAvailable,
    ) {
        if ($lineNumber < 1) {
            throw new OrderDomainException('Il numero riga della disponibilità deve essere positivo.');
        }

        if ($quantityAvailable < 0) {
            throw new OrderDomainException('La quantità disponibile non può essere negativa.');
        }
    }
}
