<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

final readonly class InboxConsumerReport
{
    public function __construct(
        public bool $consumed,
        public bool $processed,
        public bool $duplicate,
    ) {
    }
}
