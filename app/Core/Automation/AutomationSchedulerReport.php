<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

final readonly class AutomationSchedulerReport
{
    public function __construct(
        public int $recovered,
        public int $claimed,
        public int $scheduled,
        public int $failed,
    ) {
    }
}
