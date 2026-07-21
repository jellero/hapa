<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;
use Hapa\Modules\Orders\Domain\Event\OrderEvent;

trait OrderAccessors
{
    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function lastOccurredAt(): DateTimeImmutable
    {
        return $this->lastOccurredAt;
    }

    public function shippingAddress(): ?OrderAddress
    {
        return $this->shippingAddress;
    }

    public function billingAddress(): ?OrderAddress
    {
        return $this->billingAddress;
    }

    /** @return non-empty-list<OrderLine> */
    public function lines(): array
    {
        $lines = array_values($this->lines);
        if ($lines === []) {
            throw new OrderDomainException(self::LINE_REQUIRED);
        }

        return $lines;
    }

    /** @return list<OrderTransition> */
    public function transitions(): array
    {
        return $this->transitions;
    }

    /** @return list<OrderEvent> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    /** @return list<OrderEvent> */
    public function pendingEvents(): array
    {
        return $this->events;
    }

    public function clearEvents(): void
    {
        $this->events = [];
    }

}
