<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use DateTimeImmutable;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderCreated;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;
use Hapa\Modules\Orders\Domain\OrderAddressType;
use Hapa\Modules\Orders\Domain\OrderOrigin;
use Hapa\Modules\Orders\Domain\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderEventOutboxMapperTest extends TestCase
{
    public function testItCreatesAVersionedIdempotentCanonicalMessage(): void
    {
        $message = (new OrderEventOutboxMapper())->map(new OrderStatusChanged(
            'ORD-001',
            2,
            new DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            OrderStatus::Imported,
            OrderStatus::Accepted,
            null,
        ));

        self::assertSame('order.changed', $message->eventType);
        self::assertSame('order:ORD-001:v2:order.status_changed', $message->idempotencyKey);
        self::assertSame(1, $message->schemaVersion);
        self::assertSame(2, $message->payload['version']);
        self::assertSame('order.status_changed', $message->payload['change_type']);
        self::assertSame('accepted', $message->payload['status']);
        self::assertSame('imported', $message->payload['from_status']);
        self::assertArrayNotHasKey('order_version', $message->payload);
        self::assertArrayNotHasKey('to_status', $message->payload);
    }

    public function testItMapsCreationToTheCanonicalContract(): void
    {
        $message = (new OrderEventOutboxMapper())->map(new OrderCreated(
            'ORD-2026-0001',
            1,
            new DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            OrderOrigin::Marketplace,
            OrderStatus::Imported,
        ));

        self::assertSame([
            'order_number' => 'ORD-2026-0001',
            'version' => 1,
            'change_type' => 'order.created',
            'occurred_at' => '2026-07-16T10:00:00+00:00',
            'origin' => 'marketplace',
            'status' => 'imported',
        ], $message->payload);
    }

    public function testItDoesNotInventAStatusForAnAddressOnlyChange(): void
    {
        $message = (new OrderEventOutboxMapper())->map(new OrderAddressChanged(
            'ORD-2026-0001',
            3,
            new DateTimeImmutable('2026-07-16T10:02:00+00:00'),
            OrderAddressType::Shipping,
        ));

        self::assertSame('order.changed', $message->eventType);
        self::assertSame('order.address_changed', $message->payload['change_type']);
        self::assertSame('shipping', $message->payload['address_type']);
        self::assertArrayNotHasKey('status', $message->payload);
    }
}
