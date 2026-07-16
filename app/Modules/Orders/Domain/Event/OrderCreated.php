<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain\Event;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\OrderOrigin;
use Hapa\Modules\Orders\Domain\OrderStatus;

final readonly class OrderCreated extends OrderEvent
{
    public function __construct(
        string $orderNumber,
        int $version,
        DateTimeImmutable $occurredAt,
        public OrderOrigin $origin,
        public OrderStatus $status,
    ) {
        parent::__construct($orderNumber, $version, $occurredAt);
    }

    public function eventName(): string
    {
        return 'order.created';
    }
}
