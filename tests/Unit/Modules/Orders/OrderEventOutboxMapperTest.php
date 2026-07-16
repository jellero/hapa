<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use DateTimeImmutable;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;
use Hapa\Modules\Orders\Domain\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderEventOutboxMapperTest extends TestCase
{
    public function testItCreatesAVersionedIdempotentMessage(): void
    {
        $message = (new OrderEventOutboxMapper())->map(new OrderStatusChanged(
            'ORD-001',
            2,
            new DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            OrderStatus::Imported,
            OrderStatus::Accepted,
            null,
        ));

        self::assertSame('order.status_changed', $message->eventType);
        self::assertSame('order:ORD-001:v2:order.status_changed', $message->idempotencyKey);
        self::assertSame(1, $message->schemaVersion);
        self::assertSame('accepted', $message->payload['to_status']);
    }
}
