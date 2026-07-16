<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use InvalidArgumentException;

final readonly class OutboxHandlerRegistry
{
    /** @var array<string, OutboxMessageHandler> */
    private array $handlers;

    /** @param iterable<OutboxMessageHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        $indexed = [];
        foreach ($handlers as $handler) {
            foreach ($handler->eventTypes() as $eventType) {
                if (isset($indexed[$eventType])) {
                    throw new InvalidArgumentException(sprintf(
                        'Più handler sono registrati per l’evento outbox "%s".',
                        $eventType,
                    ));
                }

                $indexed[$eventType] = $handler;
            }
        }

        $this->handlers = $indexed;
    }

    public function handlerFor(string $eventType): OutboxMessageHandler
    {
        return $this->handlers[$eventType] ?? throw new UnsupportedOutboxEvent($eventType);
    }
}
