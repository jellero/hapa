<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use InvalidArgumentException;

final readonly class TrackingNotification
{
    public function __construct(
        public ExternalOrderReference $order,
        public string $carrier,
        public string $trackingNumber,
        public bool $partial,
    ) {
        if (trim($carrier) === '') {
            throw new InvalidArgumentException('Il corriere è obbligatorio.');
        }

        if (trim($trackingNumber) === '') {
            throw new InvalidArgumentException('Il numero di tracking è obbligatorio.');
        }
    }
}
