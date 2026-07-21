<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderCreated;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;
use Hapa\Modules\Orders\Domain\InvalidOrderTransition;
use Hapa\Modules\Orders\Domain\Order;
use Hapa\Modules\Orders\Domain\OrderAddress;
use Hapa\Modules\Orders\Domain\OrderDomainException;
use Hapa\Modules\Orders\Domain\OrderLine;
use Hapa\Modules\Orders\Domain\OrderLineAvailability;
use Hapa\Modules\Orders\Domain\OrderLineDecision;
use Hapa\Modules\Orders\Domain\OrderNumber;
use Hapa\Modules\Orders\Domain\OrderOrigin;
use Hapa\Modules\Orders\Domain\OrderStatus;
use Hapa\Modules\Orders\Domain\OrderTransition;
use Hapa\Modules\Orders\Domain\StaleOrderVersion;
use PHPUnit\Framework\TestCase;

final class OrderAggregateTest extends TestCase
{
    public function testItCompletesTheFullFulfilmentPathDeterministically(): void
    {
        $order = $this->order();
        $creationEvents = $order->releaseEvents();
        self::assertCount(1, $creationEvents);
        self::assertInstanceOf(OrderCreated::class, $creationEvents[0]);

        $order->attachShippingAddress($this->address(), $this->time(1));
        $order->accept($this->time(2));
        $order->submitToSpace($this->time(3));
        $order->waitForGoods($this->time(4));
        $order->recordAvailability(
            $this->time(5),
            new OrderLineAvailability(1, 2),
            new OrderLineAvailability(2, 3),
        );

        self::assertSame(OrderStatus::GoodsAvailable, $order->status());
        self::assertSame([2, 3], array_column($order->lines(), 'quantityToShip'));

        $order->startPicking($this->time(6));
        $order->completePicking($this->time(7));
        $order->registerLabel($this->time(8));
        $order->markTrackingSent($this->time(9));
        $order->completeFulfilment($this->time(10));

        self::assertSame(OrderStatus::FulfilmentCompleted, $order->status());
        self::assertSame(11, $order->version());
        self::assertCount(9, $order->transitions());

        $events = $order->releaseEvents();
        self::assertContainsOnlyInstancesOf(OrderStatusChanged::class, array_filter(
            $events,
            static fn (object $event): bool => $event instanceof OrderStatusChanged,
        ));
        self::assertNotEmpty(array_filter(
            $events,
            static fn (object $event): bool => $event instanceof OrderAvailabilityChanged,
        ));
        self::assertSame([], $order->releaseEvents());
    }

    public function testAddressWaitingReturnsToAcceptedAfterAttachingTheSnapshot(): void
    {
        $order = $this->order();
        $order->releaseEvents();
        $order->accept($this->time(1));
        $order->waitForAddress($this->time(2));
        $order->attachShippingAddress($this->address(), $this->time(3));

        self::assertSame(OrderStatus::Accepted, $order->status());
        self::assertSame(4, $order->version());
        self::assertCount(3, $order->transitions());
        self::assertNotEmpty(array_filter(
            $order->releaseEvents(),
            static fn (object $event): bool => $event instanceof OrderAddressChanged,
        ));
    }

    public function testAddressWaitingCannotBeBypassedByAcceptingAgain(): void
    {
        $order = $this->order();
        $order->accept($this->time(1));
        $order->waitForAddress($this->time(2));
        $version = $order->version();

        try {
            $order->accept($this->time(3));
            self::fail('L’attesa indirizzo non doveva essere aggirabile.');
        } catch (OrderDomainException) {
            self::assertSame($version, $order->version());
            self::assertSame(OrderStatus::WaitingAddress, $order->status());
        }
    }

    public function testItCompletesAPartialFulfilmentOnlyAfterExplicitDecision(): void
    {
        $order = $this->orderAtWaitingGoods();
        $order->recordAvailability(
            $this->time(5),
            new OrderLineAvailability(1, 2),
            new OrderLineAvailability(2, 1),
        );

        self::assertSame(OrderStatus::PartialAvailable, $order->status());

        $order->confirmPartial(
            $this->time(6),
            new OrderLineDecision(1, 2, 0),
            new OrderLineDecision(2, 1, 2),
        );
        $order->startPicking($this->time(7));
        $order->completePicking($this->time(8));
        $order->registerLabel($this->time(9));
        $order->markTrackingSent($this->time(10));
        $order->completeFulfilment($this->time(11));

        self::assertSame(OrderStatus::CompletedPartial, $order->status());
        self::assertSame([0, 2], array_column($order->lines(), 'quantityToCancel'));
    }

    public function testAvailabilityUpdateMustBeCompleteAndAtomic(): void
    {
        $order = $this->orderAtWaitingGoods();
        $version = $order->version();

        try {
            $order->recordAvailability(
                $this->time(5),
                new OrderLineAvailability(1, 2),
            );
            self::fail('La disponibilità incompleta doveva essere rifiutata.');
        } catch (OrderDomainException) {
            self::assertSame($version, $order->version());
            self::assertSame([0, 0], array_column($order->lines(), 'quantityAvailable'));
        }
    }

    public function testInvalidPartialDecisionDoesNotMutateTheOrder(): void
    {
        $order = $this->orderAtWaitingGoods();
        $order->recordAvailability(
            $this->time(5),
            new OrderLineAvailability(1, 2),
            new OrderLineAvailability(2, 1),
        );
        $version = $order->version();

        try {
            $order->confirmPartial(
                $this->time(6),
                new OrderLineDecision(1, 2, 0),
                new OrderLineDecision(2, 2, 1),
            );
            self::fail('La decisione oltre disponibilità doveva essere rifiutata.');
        } catch (OrderDomainException) {
            self::assertSame($version, $order->version());
            self::assertSame(OrderStatus::PartialAvailable, $order->status());
            self::assertSame([0, 0], array_column($order->lines(), 'quantityToShip'));
        }
    }

    public function testItRequiresShippingAddressBeforeSubmittingToSpace(): void
    {
        $order = $this->order();
        $order->accept($this->time(1));
        $version = $order->version();

        try {
            $order->submitToSpace($this->time(2));
            self::fail('L’invio senza indirizzo doveva essere rifiutato.');
        } catch (OrderDomainException) {
            self::assertSame($version, $order->version());
            self::assertSame(OrderStatus::Accepted, $order->status());
        }
    }

    public function testItRejectsArbitraryTransitions(): void
    {
        $order = $this->order();

        $this->expectException(InvalidOrderTransition::class);
        $order->registerLabel($this->time(1));
    }

    public function testManualReviewResumesOnlyThePreviousState(): void
    {
        $order = $this->order();
        $order->accept($this->time(1));
        $order->placeInManualReview('  Indirizzo ambiguo  ', $this->time(2));

        self::assertSame(OrderStatus::ManualReview, $order->status());
        self::assertSame('Indirizzo ambiguo', $order->transitions()[1]->reason);

        $order->resolveManualReview('Verifica completata', $this->time(3));
        self::assertSame(OrderStatus::Accepted, $order->status());
    }

    public function testItRejectsStaleVersionsAndEventsInThePast(): void
    {
        $order = $this->order();

        try {
            $order->assertExpectedVersion(2);
            self::fail('La versione obsoleta doveva essere rifiutata.');
        } catch (StaleOrderVersion) {
            self::assertSame(1, $order->version());
        }

        $this->expectException(OrderDomainException::class);
        $order->accept(new DateTimeImmutable('2026-07-16T09:59:59+00:00'));
    }

    public function testItCreatesFutureB2cOrdersWithoutPretendingTheStorefrontIsOperational(): void
    {
        $order = Order::b2c(
            new OrderNumber('B2C-0001'),
            'storefront-it',
            'checkout-42',
            'EUR',
            $this->time(0),
            new OrderLine(1, 'SKU-1', null, null, 1),
        );

        self::assertSame(OrderOrigin::B2cEcommerce, $order->origin);
        self::assertSame(OrderStatus::New, $order->status());
        self::assertNull($order->marketplaceId);
        self::assertSame('storefront-it', $order->originReference);
    }

    public function testItReconstitutesOnlyACoherentTransitionHistory(): void
    {
        $order = Order::reconstitute([
            'number' => new OrderNumber('HAPA-0002'), 'origin' => OrderOrigin::Marketplace, 'external_order_id' => 'external-43',
            'marketplace_id' => 10, 'origin_reference' => null, 'currency' => 'EUR', 'status' => OrderStatus::SentToSpace,
            'version' => 4, 'lines' => [new OrderLine(1, 'SKU-1', null, null, 1)], 'shipping_address' => $this->address(),
            'billing_address' => null, 'last_occurred_at' => $this->time(3), 'status_before_manual_review' => null,
            'transitions' => [
                new OrderTransition(OrderStatus::Imported, OrderStatus::Accepted, 2, $this->time(1)),
                new OrderTransition(OrderStatus::Accepted, OrderStatus::SentToSpace, 4, $this->time(3)),
            ],
        ]);

        self::assertSame(OrderStatus::SentToSpace, $order->status());
        self::assertSame(4, $order->version());
        self::assertCount(2, $order->transitions());
        self::assertSame([], $order->releaseEvents());

        $this->expectException(OrderDomainException::class);
        Order::reconstitute([
            'number' => new OrderNumber('HAPA-0003'), 'origin' => OrderOrigin::Marketplace, 'external_order_id' => 'external-44',
            'marketplace_id' => 10, 'origin_reference' => null, 'currency' => 'EUR', 'status' => OrderStatus::SentToSpace,
            'version' => 3, 'lines' => [new OrderLine(1, 'SKU-1', null, null, 1)], 'shipping_address' => $this->address(),
            'billing_address' => null, 'last_occurred_at' => $this->time(2), 'status_before_manual_review' => null,
            'transitions' => [
                new OrderTransition(OrderStatus::Imported, OrderStatus::Accepted, 2, $this->time(1)),
                new OrderTransition(OrderStatus::WaitingAddress, OrderStatus::Accepted, 3, $this->time(2)),
            ],
        ]);
    }

    private function order(): Order
    {
        return Order::marketplace(
            new OrderNumber('HAPA-0001'),
            10,
            'external-42',
            'EUR',
            $this->time(0),
            new OrderLine(1, 'SKU-1', 'LINE-1', null, 2),
            new OrderLine(2, 'SKU-2', 'LINE-2', '9781234567897', 3),
        );
    }

    private function orderAtWaitingGoods(): Order
    {
        $order = $this->order();
        $order->releaseEvents();
        $order->attachShippingAddress($this->address(), $this->time(1));
        $order->accept($this->time(2));
        $order->submitToSpace($this->time(3));
        $order->waitForGoods($this->time(4));

        return $order;
    }

    private function address(): OrderAddress
    {
        return new OrderAddress([
            'recipient' => ' Mario Rossi ', 'address_line1' => ' Via Roma 1 ', 'address_line2' => null,
            'postal_code' => '00100', 'city' => 'Roma', 'province' => 'RM', 'country_code' => 'it',
            'phone' => '+39 0612345678',
        ]);
    }

    private function time(int $minute): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('2026-07-16T10:%02d:00+00:00', $minute));
    }
}
