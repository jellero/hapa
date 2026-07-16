<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Contract;

interface CarrierAdapter
{
    public function carrier(): CarrierCode;

    public function createShipment(ShipmentRequest $shipment, string $idempotencyKey): ShipmentResult;

    public function fetchLabel(string $labelReference): string;
}
