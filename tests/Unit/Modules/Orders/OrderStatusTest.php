<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Orders;

use Hapa\Modules\Orders\Domain\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function testAvailabilityAndFulfilmentUseDistinctNames(): void
    {
        $values = array_map(
            static fn (OrderStatus $status): string => $status->value,
            OrderStatus::cases(),
        );

        self::assertContains('goods_available', $values);
        self::assertContains('fulfilment_completed', $values);
        self::assertNotContains('complete', $values);
        self::assertNotContains('completed', $values);
        self::assertContains('ready_for_carrier', $values);
        self::assertNotContains('ready_for_gls', $values);
    }
}
