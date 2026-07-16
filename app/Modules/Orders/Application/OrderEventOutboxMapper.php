<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Application;

use LogicException;
use Hapa\Core\Outbox\OutboxMessage;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderCreated;
use Hapa\Modules\Orders\Domain\Event\OrderEvent;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;

final class OrderEventOutboxMapper
{
    public function map(OrderEvent $event): OutboxMessage
    {
        $payload = [
            'order_number' => $event->orderNumber,
            'order_version' => $event->version,
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            ...$this->eventPayload($event),
        ];

        return new OutboxMessage(
            'order',
            $event->orderNumber,
            $event->eventName(),
            $payload,
            sprintf('order:%s:v%d:%s', $event->orderNumber, $event->version, $event->eventName()),
            sprintf('order-%s-v%d', $event->orderNumber, $event->version),
            $event->occurredAt,
        );
    }

    /** @return array<string, scalar|null> */
    private function eventPayload(OrderEvent $event): array
    {
        return match (true) {
            $event instanceof OrderCreated => [
                'origin' => $event->origin->value,
                'status' => $event->status->value,
            ],
            $event instanceof OrderStatusChanged => [
                'from_status' => $event->from->value,
                'to_status' => $event->to->value,
                'reason' => $event->reason,
            ],
            $event instanceof OrderAddressChanged => [
                'address_type' => $event->addressType->value,
            ],
            $event instanceof OrderAvailabilityChanged => [
                'quantity_ordered' => $event->quantityOrdered,
                'quantity_available' => $event->quantityAvailable,
                'resulting_status' => $event->resultingStatus->value,
            ],
            default => throw new LogicException(sprintf(
                'Evento ordine non supportato: %s.',
                $event::class,
            )),
        };
    }
}
