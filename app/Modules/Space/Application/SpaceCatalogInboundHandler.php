<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Application;

use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\MessageEnvelope;

final readonly class SpaceCatalogInboundHandler implements InboundMessageHandler
{
    public function __construct(private SpaceCatalogObservationHandler $handler)
    {
    }

    public function eventTypes(): array
    {
        return ['space.catalog.item.observed'];
    }

    public function handle(MessageEnvelope $message): void
    {
        $this->handler->handle($message);
    }
}
