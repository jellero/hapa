<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\TransactionManager;
use Hapa\Core\Outbox\OutboxMessage;
use Hapa\Core\Outbox\OutboxRepository;
use Throwable;

final readonly class AutomationScheduler
{
    public function __construct(
        private AutomationScheduleRepository $schedules,
        private OutboxRepository $outbox,
        private TransactionManager $transactions,
        private Clock $clock,
        private int $lockTimeoutSeconds,
    ) {
    }

    public function runDue(string $workerId, int $limit): AutomationSchedulerReport
    {
        $now = $this->clock->now();
        $recovered = $this->schedules->recoverExpired(
            $now->modify(sprintf('-%d seconds', $this->lockTimeoutSeconds)),
            $now,
        );
        $automations = $this->schedules->claimDue($workerId, min(100, $limit), $now);
        $scheduled = 0;
        $failed = 0;

        foreach ($automations as $automation) {
            try {
                $this->transactions->transactional(function () use ($automation): void {
                    $scheduledAt = $automation->scheduledAt;
                    $this->outbox->append(new OutboxMessage(
                        'automation_job',
                        $automation->code,
                        $automation->eventType,
                        [
                            'job_code' => $automation->code,
                            'scheduled_at' => $scheduledAt->format(DATE_ATOM),
                        ],
                        sprintf('automation:%s:%s', $automation->code, $scheduledAt->format('YmdHis')),
                        sprintf('scheduler-%s-%s', $automation->code, $scheduledAt->format('YmdHis')),
                        $scheduledAt,
                    ));
                    $this->schedules->complete($automation, $this->clock->now());
                });
                $scheduled++;
            } catch (LostAutomationLock $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $this->schedules->fail($automation, $this->clock->now(), $exception->getMessage());
                $failed++;
            }
        }

        return new AutomationSchedulerReport($recovered, count($automations), $scheduled, $failed);
    }
}
