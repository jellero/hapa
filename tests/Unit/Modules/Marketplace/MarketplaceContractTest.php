<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Marketplace;

use Hapa\Modules\Marketplace\Contract\ExternalOrder;
use Hapa\Modules\Marketplace\Contract\ExternalOrderLine;
use Hapa\Modules\Marketplace\Contract\ExternalOrderReference;
use Hapa\Modules\Marketplace\Contract\MarketplaceChannel;
use Hapa\Modules\Marketplace\Contract\MarketplaceConnector;
use Hapa\Modules\Marketplace\Contract\ShippingAddress;
use Hapa\Modules\Marketplace\Contract\TrackingNotification;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MarketplaceContractTest extends TestCase
{
    public function testItDefinesThePlannedSalesChannels(): void
    {
        self::assertSame(
            ['amazon', 'emag', 'temu', 'ibs'],
            array_column(MarketplaceChannel::cases(), 'value'),
        );
    }

    public function testItDistinguishesTheSellRapidoAggregatorFromDirectConnectors(): void
    {
        self::assertTrue(MarketplaceConnector::SellRapido->isAggregator());
        self::assertFalse(MarketplaceConnector::Amazon->isAggregator());
        self::assertFalse(MarketplaceConnector::Emag->isAggregator());
        self::assertFalse(MarketplaceConnector::Temu->isAggregator());
    }

    public function testItBuildsATypedExternalOrder(): void
    {
        $reference = new ExternalOrderReference(MarketplaceChannel::Amazon, 'ORDER-123');
        $line = new ExternalOrderLine('LINE-1', 'SKU-1', '9781234567897', 2);

        $order = new ExternalOrder($reference, 'EUR', [$line]);

        self::assertSame(MarketplaceChannel::Amazon, $order->reference->channel);
        self::assertSame('ORDER-123', $order->reference->externalOrderId);
        self::assertSame([$line], $order->lines);
    }

    #[DataProvider('invalidLineProvider')]
    public function testItRejectsInvalidExternalOrderLines(
        ?string $externalLineId,
        string $sku,
        ?string $ean,
        int $quantity,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new ExternalOrderLine($externalLineId, $sku, $ean, $quantity);
    }

    /** @return iterable<string, array{string|null, string, string|null, int}> */
    public static function invalidLineProvider(): iterable
    {
        yield 'empty external line id' => ['', 'SKU-1', null, 1];
        yield 'empty sku' => [null, ' ', null, 1];
        yield 'empty ean' => [null, 'SKU-1', '', 1];
        yield 'non-positive quantity' => [null, 'SKU-1', null, 0];
    }

    public function testItRejectsAnEmptyExternalOrderIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExternalOrderReference(MarketplaceChannel::Ibs, ' ');
    }

    public function testItRejectsAnInvalidCurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExternalOrder(
            new ExternalOrderReference(MarketplaceChannel::Emag, 'ORDER-123'),
            'eur',
            [new ExternalOrderLine(null, 'SKU-1', null, 1)],
        );
    }

    public function testItRejectsAnOrderWithoutLines(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ReflectionClass(ExternalOrder::class))->newInstanceArgs([
            new ExternalOrderReference(MarketplaceChannel::Temu, 'ORDER-123'),
            'EUR',
            [],
        ]);
    }

    public function testItValidatesShippingAddressBoundaries(): void
    {
        $address = new ShippingAddress(
            'Mario Rossi',
            'Via Roma 1',
            null,
            '00100',
            'Roma',
            'RM',
            'IT',
            null,
        );

        self::assertSame('IT', $address->countryCode);
    }

    public function testItRejectsAnInvalidShippingCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShippingAddress(
            'Mario Rossi',
            'Via Roma 1',
            null,
            '00100',
            'Roma',
            null,
            'ita',
            null,
        );
    }

    public function testItRejectsAnEmptyTrackingNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TrackingNotification(
            new ExternalOrderReference(MarketplaceChannel::Ibs, 'ORDER-123'),
            'GLS',
            ' ',
            false,
        );
    }
}
