<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Customers;

use Hapa\Modules\Customers\Domain\CustomerAddress;
use Hapa\Modules\Customers\Domain\CustomerCode;
use Hapa\Modules\Customers\Domain\CustomerExternalIdentity;
use Hapa\Modules\Customers\Domain\CustomerIdentitySource;
use Hapa\Modules\Customers\Domain\CustomerProfile;
use Hapa\Modules\Customers\Domain\CustomerStatus;
use Hapa\Modules\Customers\Domain\CustomerType;
use Hapa\Modules\Customers\Domain\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CustomerDomainTest extends TestCase
{
    public function testItNormalizesCanonicalIdentifiersAndEmail(): void
    {
        $code = new CustomerCode(' cust-0001 ');
        $email = new EmailAddress(' Customer@example.COM ');

        self::assertSame('CUST-0001', $code->value);
        self::assertSame('CUST-0001', (string) $code);
        self::assertSame('Customer@example.COM', $email->value);
        self::assertSame('customer@example.com', $email->normalized);
    }

    public function testItRejectsAnInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailAddress('not-an-email');
    }

    public function testBusinessProfileRequiresACompanyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CustomerProfile(
            new CustomerCode('CUST-0002'),
            CustomerStatus::Active,
            CustomerType::Business,
            'Cliente azienda',
        );
    }

    public function testItBuildsACompleteCustomerProfile(): void
    {
        $profile = new CustomerProfile(
            new CustomerCode('CUST-0003'),
            CustomerStatus::Active,
            CustomerType::Business,
            'Esempio S.r.l.',
            companyName: ' Esempio S.r.l. ',
            email: new EmailAddress('amministrazione@example.com'),
            phone: ' +39 0123 456789 ',
            vatNumber: 'IT00000000000',
        );

        self::assertSame('Esempio S.r.l.', $profile->companyName);
        self::assertSame('+39 0123 456789', $profile->phone);
        self::assertSame('it-IT', $profile->locale);
    }

    public function testItModelsAnExternalIdentityPerSourceAndAccount(): void
    {
        $identity = new CustomerExternalIdentity(
            CustomerIdentitySource::Amazon,
            ' amazon-it-main ',
            ' buyer-42 ',
        );

        self::assertSame('amazon-it-main', $identity->accountReference);
        self::assertSame('buyer-42', $identity->externalCustomerId);
        self::assertSame('b2c_ecommerce', CustomerIdentitySource::B2cEcommerce->value);
    }

    public function testItNormalizesCustomerAddresses(): void
    {
        $address = new CustomerAddress(
            ' Casa ',
            'Mario Rossi',
            'Via Roma 1',
            null,
            '00100',
            'Roma',
            'RM',
            'it',
            null,
            defaultShipping: true,
        );

        self::assertSame('Casa', $address->label);
        self::assertSame('IT', $address->countryCode);
        self::assertTrue($address->defaultShipping);
    }

    public function testInactiveAddressCannotBeDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CustomerAddress(
            'Casa',
            'Mario Rossi',
            'Via Roma 1',
            null,
            '00100',
            'Roma',
            null,
            'IT',
            null,
            active: false,
            defaultBilling: true,
        );
    }
}
