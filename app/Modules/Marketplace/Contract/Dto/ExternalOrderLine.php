<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract\Dto;

use InvalidArgumentException;

final readonly class ExternalOrderLine
{
    public function __construct(
        public string $sku,
        public int $quantity,
        public ?string $ean = null,
        public ?string $externalLineId = null,
    ) {
        if ($sku === '' || $quantity < 1) {
            throw new InvalidArgumentException('La riga ordine richiede SKU e quantità positiva.');
        }
    }
}
