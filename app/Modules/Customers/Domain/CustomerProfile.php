<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

use InvalidArgumentException;

final readonly class CustomerProfile
{
    public string $displayName;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $companyName;
    public ?string $phone;
    public ?string $taxIdentifier;
    public ?string $vatNumber;
    public string $locale;

    public function __construct(
        public CustomerCode $code,
        public CustomerStatus $status,
        public CustomerType $type,
        string $displayName,
        string|null $firstName = null,
        string|null $lastName = null,
        string|null $companyName = null,
        public ?EmailAddress $email = null,
        string|null $phone = null,
        string|null $taxIdentifier = null,
        string|null $vatNumber = null,
        string $locale = 'it-IT',
    ) {
        $this->displayName = self::required($displayName, 'nome visualizzato', 240);
        $this->firstName = self::optional($firstName, 'nome', 120);
        $this->lastName = self::optional($lastName, 'cognome', 120);
        $this->companyName = self::optional($companyName, 'ragione sociale', 240);
        $this->phone = self::optional($phone, 'telefono', 64);
        $this->taxIdentifier = self::optional($taxIdentifier, 'identificativo fiscale', 64);
        $this->vatNumber = self::optional($vatNumber, 'partita IVA', 32);

        $normalizedLocale = trim($locale);
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/D', $normalizedLocale)) {
            throw new InvalidArgumentException('La locale cliente non è valida.');
        }

        if ($type === CustomerType::Business && $this->companyName === null) {
            throw new InvalidArgumentException('La ragione sociale è obbligatoria per un cliente azienda.');
        }

        $this->locale = $normalizedLocale;
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
