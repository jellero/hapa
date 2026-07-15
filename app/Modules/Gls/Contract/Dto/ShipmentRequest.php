<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract\Dto;

use Hapa\Modules\Marketplace\Contract\Dto\ShippingAddress;
use InvalidArgumentException;

final readonly class ShipmentRequest
{
    public function __construct(
        public string $externalOrderId,
        public ShippingAddress $address,
        public int $packages,
        public string $weightKg,
    ) {
        if ($externalOrderId === '' || $packages < 1 || !preg_match('/^\d{1,7}(?:\.\d{1,3})?$/D', $weightKg)) {
            throw new InvalidArgumentException('Richiesta spedizione GLS non valida.');
        }
    }
}
