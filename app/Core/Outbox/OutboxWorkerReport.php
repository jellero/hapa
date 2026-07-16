<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

final readonly class OutboxWorkerReport
{
    public function __construct(
        public int $recovered,
        public int $claimed,
        public int $completed,
        public int $retried,
        public int $dead,
    ) {
    }
}
