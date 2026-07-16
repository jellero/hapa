<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain\Event;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\OrderDomainException;
use Hapa\Modules\Orders\Domain\OrderStatus;

final readonly class OrderAvailabilityChanged extends OrderEvent
{
    public function __construct(
        string $orderNumber,
        int $version,
        DateTimeImmutable $occurredAt,
        public int $quantityOrdered,
        public int $quantityAvailable,
        public OrderStatus $resultingStatus,
    ) {
        if ($quantityOrdered < 1 || $quantityAvailable < 0 || $quantityAvailable > $quantityOrdered) {
            throw new OrderDomainException('Il riepilogo disponibilità dell’evento non è coerente.');
        }

        parent::__construct($orderNumber, $version, $occurredAt);
    }

    public function eventName(): string
    {
        return 'order.availability_changed';
    }
}
