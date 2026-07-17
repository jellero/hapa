<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

interface MessagePublisher
{
    public function publish(string $exchangeName, string $routingKey, MessageEnvelope $message): void;
}
