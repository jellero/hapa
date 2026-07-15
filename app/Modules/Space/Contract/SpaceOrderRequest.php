<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

final readonly class SpaceOrderRequest
{
    /** @param list<array{sku: string, quantity: int}> $lines */
    public function __construct(
        public string $orderReference,
        public array $lines,
    ) {
    }
}
