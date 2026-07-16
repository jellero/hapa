<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain\Event;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\OrderStatus;

final readonly class OrderStatusChanged extends OrderEvent
{
    public function __construct(
        string $orderNumber,
        int $version,
        DateTimeImmutable $occurredAt,
        public OrderStatus $from,
        public OrderStatus $to,
        public ?string $reason,
    ) {
        parent::__construct($orderNumber, $version, $occurredAt);
    }

    public function eventName(): string
    {
        return 'order.status_changed';
    }
}
