<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

use Hapa\Modules\Gls\Contract\Dto\ShipmentRequest;
use Hapa\Modules\Gls\Contract\Dto\ShipmentResult;

interface GlsAdapter
{
    public function createShipment(ShipmentRequest $shipment, string $idempotencyKey): ShipmentResult;

    public function fetchLabel(string $labelReference): string;
}
