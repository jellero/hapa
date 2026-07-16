<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

use DateTimeImmutable;

interface AutomationScheduleRepository
{
    /** @return list<ScheduledAutomation> */
    public function claimDue(string $workerId, int $limit, DateTimeImmutable $now): array;

    public function complete(ScheduledAutomation $automation, DateTimeImmutable $completedAt): void;

    public function fail(ScheduledAutomation $automation, DateTimeImmutable $failedAt, string $error): void;

    public function recoverExpired(DateTimeImmutable $expiredBefore, DateTimeImmutable $now): int;
}
