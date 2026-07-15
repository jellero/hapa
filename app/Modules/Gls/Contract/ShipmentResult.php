<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

final readonly class ShipmentResult
{
    public function __construct(
        public string $shipmentId,
        public string $trackingNumber,
        public string $labelReference,
    ) {
    }
}
