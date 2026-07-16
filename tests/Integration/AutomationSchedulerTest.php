<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Automation\AutomationScheduler;
use Hapa\Core\Automation\PostgresAutomationScheduleRepository;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class AutomationSchedulerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
            $this->pdo->beginTransaction();
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testItSchedulesOnlyEnabledDueJobsAndAdvancesTheWatermark(): void
    {
        $this->pdo->exec(<<<'SQL'
UPDATE automation_jobs
SET enabled = TRUE,
    next_run_at = '2026-07-16 09:50:00+00',
    last_status = 'idle'
WHERE code = 'accept_complete_orders'
SQL);
        $clock = new FrozenClock(new DateTimeImmutable('2026-07-16T10:00:00+00:00'));
        $scheduler = new AutomationScheduler(
            new PostgresAutomationScheduleRepository($this->pdo),
            new PostgresOutboxRepository($this->pdo),
            new PdoTransactionManager($this->pdo),
            $clock,
            300,
        );

        $report = $scheduler->runDue('scheduler-integration', 10);

        self::assertSame(1, $report->claimed);
        self::assertSame(1, $report->scheduled);
        self::assertSame(0, $report->failed);

        $statement = $this->pdo->query(<<<'SQL'
SELECT enabled, last_status, next_run_at > '2026-07-16 10:00:00+00' AS advanced
FROM automation_jobs
WHERE code = 'accept_complete_orders'
SQL);
        self::assertNotFalse($statement);
        /** @var array{enabled: bool|string, last_status: string, advanced: bool|string}|false $job */
        $job = $statement->fetch();
        self::assertIsArray($job);
        self::assertSame('success', $job['last_status']);
        self::assertContains($job['advanced'], [true, '1', 't']);

        $statement = $this->pdo->query(<<<'SQL'
SELECT event_type, status
FROM outbox_messages
WHERE aggregate_type = 'automation_job'
  AND aggregate_id = 'accept_complete_orders'
SQL);
        self::assertNotFalse($statement);
        /** @var array{event_type: string, status: string}|false $message */
        $message = $statement->fetch();
        self::assertIsArray($message);
        self::assertSame('automation.orders.accept_complete.requested', $message['event_type']);
        self::assertSame('pending', $message['status']);

        self::assertSame(0, $scheduler->runDue('scheduler-integration', 10)->claimed);
    }
}
