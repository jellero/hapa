<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

interface SpaceAdapter
{
    public function submitOrder(SpaceOrderRequest $order, string $idempotencyKey): string;

    /** @return list<AvailabilityLine> */
    public function fetchAvailability(string $spaceOrderId): array;
}
