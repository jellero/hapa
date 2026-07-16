<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

use InvalidArgumentException;

final readonly class CustomerAddress
{
    public string $label;
    public string $recipient;
    public string $addressLine1;
    public ?string $addressLine2;
    public string $postalCode;
    public string $city;
    public ?string $province;
    public string $countryCode;
    public ?string $phone;

    public function __construct(
        string $label,
        string $recipient,
        string $addressLine1,
        ?string $addressLine2,
        string $postalCode,
        string $city,
        ?string $province,
        string $countryCode,
        ?string $phone,
        public bool $active = true,
        public bool $defaultShipping = false,
        public bool $defaultBilling = false,
    ) {
        $this->label = self::required($label, 'etichetta', 80);
        $this->recipient = self::required($recipient, 'destinatario', 240);
        $this->addressLine1 = self::required($addressLine1, 'indirizzo', 240);
        $this->addressLine2 = self::optional($addressLine2, 'seconda riga indirizzo', 240);
        $this->postalCode = self::required($postalCode, 'codice postale', 32);
        $this->city = self::required($city, 'città', 160);
        $this->province = self::optional($province, 'provincia', 120);
        $this->phone = self::optional($phone, 'telefono', 64);

        $normalizedCountryCode = strtoupper(trim($countryCode));
        if (!preg_match('/^[A-Z]{2}$/D', $normalizedCountryCode)) {
            throw new InvalidArgumentException('Il paese deve rispettare il formato ISO 3166-1 alpha-2.');
        }

        if (!$active && ($defaultShipping || $defaultBilling)) {
            throw new InvalidArgumentException('Un indirizzo non attivo non può essere predefinito.');
        }

        $this->countryCode = $normalizedCountryCode;
    }

    private static function required(string $value, string $field, int $maximumLength): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new InvalidArgumentException(sprintf('Il campo %s è obbligatorio e non può superare %d caratteri.', $field, $maximumLength));
        }

        return $normalized;
    }

    private static function optional(?string $value, string $field, int $maximumLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new InvalidArgumentException(sprintf('Il campo %s non può essere vuoto o superare %d caratteri.', $field, $maximumLength));
        }

        return $normalized;
    }
}
