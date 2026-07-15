<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract\Dto;

use InvalidArgumentException;

final readonly class SpaceOrderLine
{
    public function __construct(public string $sku, public int $quantity)
    {
        if ($sku === '' || $quantity < 1) {
            throw new InvalidArgumentException('Riga ordine Space non valida.');
        }
    }
}
