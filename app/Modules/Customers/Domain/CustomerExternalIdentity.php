<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

use InvalidArgumentException;

final readonly class CustomerExternalIdentity
{
    public string $accountReference;
    public string $externalCustomerId;

    public function __construct(
        public CustomerIdentitySource $source,
        string $accountReference,
        string $externalCustomerId,
    ) {
        $this->accountReference = self::required($accountReference, 'account sorgente', 160);
        $this->externalCustomerId = self::required($externalCustomerId, 'identificativo cliente esterno', 160);
    }

    private static function required(string $value, string $field, int $maximumLength): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new InvalidArgumentException(sprintf('Il campo %s è obbligatorio e non può superare %d caratteri.', $field, $maximumLength));
        }

        return $normalized;
    }
}
