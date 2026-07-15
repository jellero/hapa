<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

interface GlsAdapter
{
    public function createShipment(ShipmentRequest $shipment, string $idempotencyKey): ShipmentResult;

    public function fetchLabel(string $labelReference): string;
}
