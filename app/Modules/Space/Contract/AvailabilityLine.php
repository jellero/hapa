<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

final readonly class AvailabilityLine
{
    public function __construct(
        public string $sku,
        public int $requested,
        public int $available,
        public int $missing,
    ) {
    }
}
