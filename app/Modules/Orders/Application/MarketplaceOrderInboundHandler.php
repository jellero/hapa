<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Application;

use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\MessageEnvelope;

final readonly class MarketplaceOrderInboundHandler implements InboundMessageHandler
{
    public function __construct(private MarketplaceOrderObservationHandler $handler)
    {
    }

    public function eventTypes(): array
    {
        return ['marketplace.order.observed'];
    }

    public function handle(MessageEnvelope $message): void
    {
        $this->handler->handle($message);
    }
}
