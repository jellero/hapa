<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract\Dto;

use InvalidArgumentException;

final readonly class SpaceOrderRequest
{
    /** @param list<SpaceOrderLine> $lines */
    public function __construct(
        public string $externalOrderId,
        public array $lines,
    ) {
        if ($externalOrderId === '' || $lines === []) {
            throw new InvalidArgumentException('Richiesta ordine Space non valida.');
        }
    }
}
