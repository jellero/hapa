<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Retry = 'retry';
    case Completed = 'completed';
    case Dead = 'dead';
}
