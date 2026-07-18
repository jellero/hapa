<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Application;

final readonly class MarketplaceOrderIngestionResult
{
    public function __construct(
        public int $observationId,
        public ?int $orderId,
        public string $outcome,
        public ?string $reason = null,
    ) {
    }
}
