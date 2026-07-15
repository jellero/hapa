<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract\Dto;

use InvalidArgumentException;

final readonly class TrackingNotification
{
    public function __construct(
        public string $externalOrderId,
        public string $carrier,
        public string $trackingNumber,
        public bool $partial,
    ) {
        if ($externalOrderId === '' || $carrier === '' || $trackingNumber === '') {
            throw new InvalidArgumentException('Notifica tracking non valida.');
        }
    }
}
