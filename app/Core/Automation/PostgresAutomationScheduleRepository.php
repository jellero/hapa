<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class PostgresAutomationScheduleRepository implements AutomationScheduleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function claimDue(string $workerId, int $limit, DateTimeImmutable $now): array
    {
        if (trim($workerId) === '' || $limit < 1 || $limit > 100) {
            throw new RuntimeException('Worker identity e limite scheduler devono essere validi.');
        }

        $lockToken = bin2hex(random_bytes(16));
        $statement = $this->pdo->prepare(<<<'SQL'
WITH due AS (
    SELECT id
    FROM automation_jobs
    WHERE enabled
      AND next_run_at <= :due_at
      AND last_status <> 'running'
    ORDER BY next_run_at, id
    FOR UPDATE SKIP LOCKED
    LIMIT :batch_limit
)
UPDATE automation_jobs AS job
SET last_status = 'running',
    last_started_at = :started_at,
    locked_at = :locked_at,
    locked_by = :worker_id,
    lock_token = :lock_token,
    last_error = NULL,
    updated_at = :updated_at
FROM due
WHERE job.id = due.id
RETURNING job.id, job.code, job.event_type, job.interval_seconds,
          job.next_run_at, job.locked_by, job.lock_token
SQL);
        $statement->bindValue('due_at', self::date($now));
        $statement->bindValue('batch_limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('started_at', self::date($now));
        $statement->bindValue('locked_at', self::date($now));
        $statement->bindValue('worker_id', $workerId);
        $statement->bindValue('lock_token', $lockToken);
        $statement->bindValue('updated_at', self::date($now));
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_map(static fn (array $row): ScheduledAutomation => new ScheduledAutomation(
            (int) $row['id'],
            (string) $row['code'],
            (string) $row['event_type'],
            (int) $row['interval_seconds'],
            new DateTimeImmutable((string) $row['next_run_at']),
            (string) $row['locked_by'],
            (string) $row['lock_token'],
        ), $rows);
    }

    public function complete(ScheduledAutomation $automation, DateTimeImmutable $completedAt): void
    {
        $nextRunAt = $automation->scheduledAt;
        do {
            $nextRunAt = $nextRunAt->modify(sprintf('+%d seconds', $automation->intervalSeconds));
        } while ($nextRunAt <= $completedAt);

        $this->finish(
            $automation,
            "last_status = 'success', last_completed_at = :result_at, next_run_at = :next_run_at, last_error = NULL",
            $completedAt,
            ['next_run_at' => self::date($nextRunAt)],
        );
    }

    public function fail(ScheduledAutomation $automation, DateTimeImmutable $failedAt, string $error): void
    {
        $this->finish(
            $automation,
            "last_status = 'error', last_error = :last_error, last_started_at = COALESCE(last_started_at, :result_at)",
            $failedAt,
            ['last_error' => substr(trim($error), 0, 8000)],
        );
    }

    public function recoverExpired(DateTimeImmutable $expiredBefore, DateTimeImmutable $now): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE automation_jobs
SET last_status = 'error',
    last_error = 'Lock scheduler scaduto: job reso nuovamente eseguibile.',
    locked_at = NULL,
    locked_by = NULL,
    lock_token = NULL,
    updated_at = :now
WHERE last_status = 'running'
  AND locked_at < :expired_before
SQL);
        $statement->execute([
            'now' => self::date($now),
            'expired_before' => self::date($expiredBefore),
        ]);

        return $statement->rowCount();
    }

    /** @param array<string, string> $parameters */
    private function finish(
        ScheduledAutomation $automation,
        string $changes,
        DateTimeImmutable $finishedAt,
        array $parameters,
    ): void {
        $statement = $this->pdo->prepare(sprintf(<<<'SQL'
UPDATE automation_jobs
SET %s,
    locked_at = NULL,
    locked_by = NULL,
    lock_token = NULL,
    updated_at = :updated_at
WHERE id = :id
  AND last_status = 'running'
  AND locked_by = :worker_id
  AND lock_token = :lock_token
SQL, $changes));
        $statement->execute([
            'result_at' => self::date($finishedAt),
            'updated_at' => self::date($finishedAt),
            'id' => $automation->id,
            'worker_id' => $automation->workerId,
            'lock_token' => $automation->lockToken,
            ...$parameters,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new LostAutomationLock($automation->code);
        }
    }

    private static function date(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.uP');
    }
}
