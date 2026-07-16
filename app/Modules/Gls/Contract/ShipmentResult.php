<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

use InvalidArgumentException;

final readonly class ShipmentResult
{
    public function __construct(
        public string $shipmentId,
        public string $trackingNumber,
        public string $labelReference,
    ) {
        foreach (
            [
                'identificativo spedizione' => $shipmentId,
                'numero di tracking' => $trackingNumber,
                'riferimento etichetta' => $labelReference,
            ] as $field => $value
        ) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo %s è obbligatorio.', $field));
            }
        }
    }
}
