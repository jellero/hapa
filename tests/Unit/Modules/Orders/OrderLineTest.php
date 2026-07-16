<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use Hapa\Modules\Orders\Domain\OrderDomainException;
use Hapa\Modules\Orders\Domain\OrderLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderLineTest extends TestCase
{
    public function testItNormalizesIdentifiersAndPlansACompleteLine(): void
    {
        $line = new OrderLine(1, ' SKU-1 ', ' LINE-1 ', ' 9781234567897 ', 2);
        $available = $line->withAvailability(2);
        $planned = $available->withFullFulfilment();

        self::assertSame('SKU-1', $line->sku);
        self::assertSame('LINE-1', $line->externalLineId);
        self::assertSame('9781234567897', $line->ean);
        self::assertTrue($planned->isFullyAvailable());
        self::assertTrue($planned->isFulfilmentPlanned());
        self::assertSame(2, $planned->quantityToShip);
        self::assertSame(0, $planned->quantityToCancel);
    }

    public function testAvailabilityRefreshClearsAnObsoletePlan(): void
    {
        $line = (new OrderLine(1, 'SKU-1', null, null, 3))
            ->withAvailability(3)
            ->withFullFulfilment()
            ->withAvailability(1);

        self::assertSame(1, $line->quantityAvailable);
        self::assertSame(0, $line->quantityToShip);
        self::assertFalse($line->isFulfilmentPlanned());
    }

    public function testAPartialDecisionMustCoverTheWholeOrderedQuantity(): void
    {
        $line = (new OrderLine(1, 'SKU-1', null, null, 3))->withAvailability(2);

        $this->expectException(OrderDomainException::class);
        $line->withDecision(1, 1);
    }

    /** @param array{int, string, string|null, string|null, int, int, int, int} $arguments */
    #[DataProvider('invalidLines')]
    public function testItRejectsInvalidLineState(array $arguments): void
    {
        $this->expectException(OrderDomainException::class);

        new OrderLine(...$arguments);
    }

    /** @return iterable<string, array{array{int, string, string|null, string|null, int, int, int, int}}> */
    public static function invalidLines(): iterable
    {
        yield 'numero riga nullo' => [[0, 'SKU-1', null, null, 1, 0, 0, 0]];
        yield 'sku vuoto' => [[1, ' ', null, null, 1, 0, 0, 0]];
        yield 'quantità ordinata nulla' => [[1, 'SKU-1', null, null, 0, 0, 0, 0]];
        yield 'disponibilità eccessiva' => [[1, 'SKU-1', null, null, 2, 3, 0, 0]];
        yield 'spedizione oltre disponibilità' => [[1, 'SKU-1', null, null, 2, 1, 2, 0]];
        yield 'piano oltre ordinato' => [[1, 'SKU-1', null, null, 2, 2, 2, 1]];
        yield 'quantità negativa' => [[1, 'SKU-1', null, null, 2, -1, 0, 0]];
    }
}
