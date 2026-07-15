<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

final readonly class ShippingAddress
{
    public function __construct(
        public string $recipient,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $postalCode,
        public string $city,
        public ?string $province,
        public string $countryCode,
        public ?string $phone,
    ) {
    }
}
