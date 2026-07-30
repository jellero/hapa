<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Integration\ProviderSecretFields;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProviderSecretFieldsTest extends TestCase
{
    public function testItReturnsProviderSpecificFieldsAndDropsBlankValues(): void
    {
        $fields = new ProviderSecretFields();

        self::assertArrayHasKey('lwa_refresh_token', $fields->forProvider('amazon'));
        self::assertSame(
            ['api_key' => 'Token Bearer elenco fornitori Space (/apie)'],
            $fields->forAccount('space', ['suppliers.read']),
        );
        self::assertSame(
            ['api_key' => 'Token Bearer feed prodotti Space (/apih)'],
            $fields->forAccount('space', ['catalog.read']),
        );
        self::assertSame(
            ['username' => 'operator'],
            $fields->submitted('sellrapido', ['username' => ' operator ', 'password' => '']),
        );
    }

    public function testItRequiresAtLeastOneValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ProviderSecretFields())->submitted('gls', ['password' => '']);
    }
}
