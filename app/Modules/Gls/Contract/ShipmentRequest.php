<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

use InvalidArgumentException;

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
        foreach (
            [
                'riferimento ordine' => $orderReference,
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

        foreach (['seconda riga indirizzo' => $addressLine2, 'provincia' => $province] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo %s non può essere vuoto.', $field));
            }
        }

        if ($packages < 1) {
            throw new InvalidArgumentException('Il numero di colli deve essere positivo.');
        }

        if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/D', $weightKg) || (float) $weightKg <= 0) {
            throw new InvalidArgumentException('Il peso deve essere un decimale positivo con massimo tre cifre decimali.');
        }

        if (!preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new InvalidArgumentException('Il paese deve rispettare il formato ISO 3166-1 alpha-2 maiuscolo.');
        }
    }
}
