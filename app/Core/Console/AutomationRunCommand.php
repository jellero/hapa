<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Closure;
use Hapa\Core\Automation\AutomationScheduler;
use Hapa\Core\Outbox\OutboxWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'automation:run',
    description: 'Pianifica i job dovuti ed elabora un batch della transactional outbox.',
)]
final class AutomationRunCommand extends Command
{
    /**
     * @param Closure(): AutomationScheduler $schedulerFactory
     * @param Closure(): OutboxWorker $workerFactory
     */
    public function __construct(
        private readonly Closure $schedulerFactory,
        private readonly Closure $workerFactory,
        private readonly int $defaultBatchSize,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('worker', null, InputOption::VALUE_REQUIRED, 'Identità stabile del worker per lock e diagnostica.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Numero massimo di job/messaggi da elaborare.', (string) $this->defaultBatchSize);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);
        if ($limit === false) {
            $io->error('L’opzione --limit deve essere compresa tra 1 e 500.');

            return Command::INVALID;
        }

        $configuredWorker = $input->getOption('worker');
        $workerId = is_string($configuredWorker) && trim($configuredWorker) !== ''
            ? trim($configuredWorker)
            : $this->workerIdentity();

        $schedule = ($this->schedulerFactory)()->runDue($workerId, $limit);
        $outbox = ($this->workerFactory)()->runOnce($workerId, $limit);

        $io->table(
            ['Componente', 'Recuperati', 'Reclamati', 'Completati', 'Retry/KO', 'Dead letter'],
            [
                ['Scheduler', $schedule->recovered, $schedule->claimed, $schedule->scheduled, $schedule->failed, '—'],
                ['Outbox', $outbox->recovered, $outbox->claimed, $outbox->completed, $outbox->retried, $outbox->dead],
            ],
        );

        if ($schedule->failed > 0 || $outbox->dead > 0) {
            $io->warning('Esecuzione conclusa con elementi che richiedono verifica operativa.');

            return Command::FAILURE;
        }

        $io->success(sprintf('Automazioni elaborate dal worker %s.', $workerId));

        return Command::SUCCESS;
    }

    private function workerIdentity(): string
    {
        $hostname = gethostname();

        return sprintf(
            '%s:%d:%s',
            $hostname === false ? 'hapa' : $hostname,
            getmypid() ?: 0,
            bin2hex(random_bytes(4)),
        );
    }
}
