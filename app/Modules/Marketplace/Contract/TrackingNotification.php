<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

final readonly class TrackingNotification
{
    public function __construct(
        public string $externalOrderId,
        public string $carrier,
        public string $trackingNumber,
        public bool $partial,
    ) {
    }
}
