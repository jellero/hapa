<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

interface InboundMessageHandler
{
    /** @return non-empty-list<string> */
    public function eventTypes(): array;

    public function handle(MessageEnvelope $message): void;
}
