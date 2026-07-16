<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use RuntimeException;

final class UnsupportedInboundMessage extends RuntimeException
{
    public function __construct(string $eventType)
    {
        parent::__construct(sprintf('Evento RabbitMQ inbound non supportato: %s', $eventType));
    }
}
