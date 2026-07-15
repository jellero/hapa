<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract\Dto;

use InvalidArgumentException;

final readonly class ShipmentResult
{
    public function __construct(
        public string $shipmentId,
        public string $trackingNumber,
        public string $labelReference,
    ) {
        if ($shipmentId === '' || $trackingNumber === '' || $labelReference === '') {
            throw new InvalidArgumentException('Risultato spedizione GLS non valido.');
        }
    }
}
