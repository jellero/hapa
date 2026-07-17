<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use InvalidArgumentException;

final readonly class InboundMessageHandlerRegistry
{
    /** @var array<string, InboundMessageHandler> */
    private array $handlers;

    /** @param iterable<InboundMessageHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        $indexed = [];
        foreach ($handlers as $handler) {
            foreach ($handler->eventTypes() as $eventType) {
                $normalized = trim($eventType);
                if ($normalized === '') {
                    throw new InvalidArgumentException('Un handler inbound dichiara un event type vuoto.');
                }
                if (isset($indexed[$normalized])) {
                    throw new InvalidArgumentException(sprintf(
                        'Più handler inbound dichiarano l’event type %s.',
                        $normalized,
                    ));
                }

                $indexed[$normalized] = $handler;
            }
        }

        $this->handlers = $indexed;
    }

    public function handle(MessageEnvelope $message): void
    {
        $handler = $this->handlers[$message->eventType] ?? null;
        if ($handler === null) {
            throw new UnsupportedInboundMessage($message->eventType);
        }

        $handler->handle($message);
    }

    public function supports(string $eventType): bool
    {
        return isset($this->handlers[$eventType]);
    }
}
