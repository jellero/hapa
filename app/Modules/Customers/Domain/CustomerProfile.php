<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

use InvalidArgumentException;

final readonly class CustomerProfile
{
    public CustomerCode $code;
    public CustomerStatus $status;
    public CustomerType $type;
    public ?EmailAddress $email;
    public string $displayName;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $companyName;
    public ?string $phone;
    public ?string $taxIdentifier;
    public ?string $vatNumber;
    public string $locale;

    /** @param array{code:CustomerCode,status:CustomerStatus,type:CustomerType,display_name:string,first_name?:?string,last_name?:?string,company_name?:?string,email?:?EmailAddress,phone?:?string,tax_identifier?:?string,vat_number?:?string,locale?:string} $data */
    public function __construct(array $data)
    {
        $this->code = $data['code'];
        $this->status = $data['status'];
        $this->type = $data['type'];
        $this->email = $data['email'] ?? null;
        $displayName = $data['display_name']; $firstName = $data['first_name'] ?? null; $lastName = $data['last_name'] ?? null;
        $companyName = $data['company_name'] ?? null; $phone = $data['phone'] ?? null;
        $taxIdentifier = $data['tax_identifier'] ?? null; $vatNumber = $data['vat_number'] ?? null; $locale = $data['locale'] ?? 'it-IT';
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

        if ($this->type === CustomerType::Business && $this->companyName === null) {
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
