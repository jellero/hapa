<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain\Event;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\OrderDomainException;

abstract readonly class OrderEvent
{
    public string $orderNumber;

    public function __construct(
        string $orderNumber,
        public int $version,
        public DateTimeImmutable $occurredAt,
    ) {
        $normalized = trim($orderNumber);
        if ($normalized === '') {
            throw new OrderDomainException('Il numero ordine dell’evento è obbligatorio.');
        }

        if ($version < 1) {
            throw new OrderDomainException('La versione dell’evento deve essere positiva.');
        }

        $this->orderNumber = $normalized;
    }

    abstract public function eventName(): string;
}
