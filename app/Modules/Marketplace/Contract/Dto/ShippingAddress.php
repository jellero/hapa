<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract\Dto;

use InvalidArgumentException;

final readonly class ShippingAddress
{
    public function __construct(
        public string $recipient,
        public string $addressLine1,
        public string $city,
        public string $postalCode,
        public string $countryCode,
        public ?string $addressLine2 = null,
        public ?string $province = null,
        public ?string $phone = null,
    ) {
        if (
            $recipient === ''
            || $addressLine1 === ''
            || $city === ''
            || $postalCode === ''
            || !preg_match('/^[A-Z]{2}$/D', $countryCode)
        ) {
            throw new InvalidArgumentException('Indirizzo di spedizione non valido.');
        }
    }
}
