<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use InvalidArgumentException;

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
        foreach (
            [
                'destinatario' => $recipient,
                'indirizzo' => $addressLine1,
                'codice postale' => $postalCode,
                'città' => $city,
            ] as $field => $value
        ) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo %s è obbligatorio.', $field));
            }
        }

        foreach (
            [
                'seconda riga indirizzo' => $addressLine2,
                'provincia' => $province,
                'telefono' => $phone,
            ] as $field => $value
        ) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo %s non può essere vuoto.', $field));
            }
        }

        if (!preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new InvalidArgumentException('Il paese deve rispettare il formato ISO 3166-1 alpha-2 maiuscolo.');
        }
    }
}
