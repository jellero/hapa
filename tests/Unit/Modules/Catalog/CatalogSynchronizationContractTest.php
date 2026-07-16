<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Catalog;

use DateTimeImmutable;
use Hapa\Modules\Catalog\Contract\Money;
use Hapa\Modules\Marketplace\Contract\MarketplaceChannel;
use Hapa\Modules\Marketplace\Contract\MarketplaceOfferPublication;
use Hapa\Modules\Marketplace\Contract\MarketplaceOfferUpdate;
use Hapa\Modules\Space\Contract\SpaceCatalogBatch;
use Hapa\Modules\Space\Contract\SpaceCatalogCursor;
use Hapa\Modules\Space\Contract\SpaceCatalogItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CatalogSynchronizationContractTest extends TestCase
{
    public function testItCarriesATypedSpaceCatalogPageIntoAMarketplaceOffer(): void
    {
        $time = new DateTimeImmutable('2026-07-16T10:00:00+00:00');
        $spaceItem = new SpaceCatalogItem(
            'SKU-1',
            'SPACE-1',
            new Money(1_999, 'EUR'),
            12,
            'space-version-42',
            $time,
        );
        $batch = new SpaceCatalogBatch(
            [$spaceItem],
            new SpaceCatalogCursor('next-page'),
            true,
        );
        $offer = new MarketplaceOfferUpdate(
            MarketplaceChannel::Amazon,
            $spaceItem->sku,
            null,
            new Money(2_299, 'EUR'),
            10,
            'hapa-version-7',
        );
        $publication = new MarketplaceOfferPublication('AMZ-OFFER-1', 'remote-8', $time);

        self::assertSame([$spaceItem], $batch->items);
        self::assertSame('next-page', $batch->nextCursor->token);
        self::assertSame(10, $offer->availableQuantity);
        self::assertSame('remote-8', $publication->remoteVersion);
    }

    public function testItRejectsANegativeSpaceAvailability(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpaceCatalogItem(
            'SKU-1',
            'SPACE-1',
            new Money(1_999, 'EUR'),
            -1,
            'space-version-42',
            new DateTimeImmutable(),
        );
    }

    public function testItRejectsAnEmptyMarketplaceSourceVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MarketplaceOfferUpdate(
            MarketplaceChannel::Emag,
            'SKU-1',
            null,
            new Money(2_299, 'EUR'),
            10,
            ' ',
        );
    }
}
