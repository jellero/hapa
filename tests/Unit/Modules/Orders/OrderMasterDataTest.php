<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use Hapa\Modules\Orders\Domain\OrderNumber;
use Hapa\Modules\Orders\Domain\OrderOrigin;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OrderMasterDataTest extends TestCase
{
    public function testItNormalizesTheCanonicalOrderNumber(): void
    {
        $number = new OrderNumber(' hapa-00001234 ');

        self::assertSame('HAPA-00001234', $number->value);
        self::assertSame('HAPA-00001234', (string) $number);
    }

    public function testItRejectsAnInvalidOrderNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OrderNumber('invalid order number');
    }

    public function testItDistinguishesMarketplaceAndFutureB2cOrigins(): void
    {
        self::assertSame('marketplace', OrderOrigin::Marketplace->value);
        self::assertSame('b2c_ecommerce', OrderOrigin::B2cEcommerce->value);
    }
}
