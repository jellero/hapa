<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

final readonly class OutboxRelayReport
{
    public function __construct(
        public int $recovered,
        public int $claimed,
        public int $published,
        public int $retried,
        public int $dead,
    ) {
    }
}
