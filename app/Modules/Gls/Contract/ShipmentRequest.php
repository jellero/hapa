<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

final readonly class ShipmentRequest
{
    public function __construct(
        public string $orderReference,
        public int $packages,
        public string $weightKg,
        public string $recipient,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $postalCode,
        public string $city,
        public ?string $province,
        public string $countryCode,
    ) {
    }
}
