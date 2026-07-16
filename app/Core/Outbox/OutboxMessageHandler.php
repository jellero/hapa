<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

interface OutboxMessageHandler
{
    /** @return non-empty-list<string> */
    public function eventTypes(): array;

    public function handle(ClaimedOutboxMessage $message): void;
}
