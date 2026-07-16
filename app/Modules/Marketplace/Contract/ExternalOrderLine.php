<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use InvalidArgumentException;

final readonly class ExternalOrderLine
{
    public function __construct(
        public ?string $externalLineId,
        public string $sku,
        public ?string $ean,
        public int $quantity,
    ) {
        if ($externalLineId !== null && trim($externalLineId) === '') {
            throw new InvalidArgumentException('L’identificativo riga esterno non può essere vuoto.');
        }

        if (trim($sku) === '') {
            throw new InvalidArgumentException('Lo SKU è obbligatorio.');
        }

        if ($ean !== null && trim($ean) === '') {
            throw new InvalidArgumentException('L’EAN non può essere vuoto.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('La quantità ordinata deve essere positiva.');
        }
    }
}
