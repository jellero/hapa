<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain\Event;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\OrderAddressType;

final readonly class OrderAddressChanged extends OrderEvent
{
    public function __construct(
        string $orderNumber,
        int $version,
        DateTimeImmutable $occurredAt,
        public OrderAddressType $addressType,
    ) {
        parent::__construct($orderNumber, $version, $occurredAt);
    }

    public function eventName(): string
    {
        return 'order.address_changed';
    }
}
