<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

use DateTimeImmutable;

final readonly class ScheduledAutomation
{
    public function __construct(
        public int $id,
        public string $code,
        public string $eventType,
        public int $intervalSeconds,
        public DateTimeImmutable $scheduledAt,
        public string $workerId,
        public string $lockToken,
    ) {
    }
}
