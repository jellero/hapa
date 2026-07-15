<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

interface SpaceAdapter
{
    /** @param array<string, mixed> $order */
    public function submitOrder(array $order, string $idempotencyKey): string;

    /** @return list<array{sku: string, requested: int, available: int, missing: int}> */
    public function fetchAvailability(string $spaceOrderId): array;
}
