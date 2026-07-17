<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

interface MessageReceiver
{
    /** @param callable(MessageEnvelope): void $consumer */
    public function consumeOne(callable $consumer): bool;
}
