<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use Hapa\Modules\Space\Contract\Dto\AvailabilityResult;
use Hapa\Modules\Space\Contract\Dto\SpaceOrderRequest;

interface SpaceAdapter
{
    public function submitOrder(SpaceOrderRequest $order, string $idempotencyKey): string;

    /** @return list<AvailabilityResult> */
    public function fetchAvailability(string $spaceOrderId): array;
}
