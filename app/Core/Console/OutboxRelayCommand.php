<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Configuration\RabbitMqConfig;
use Hapa\Core\Outbox\OutboxRelayFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'outbox:relay',
    description: 'Pubblica su RabbitMQ un batch della transactional outbox HAPA.',
)]
final class OutboxRelayCommand extends Command
{
    public function __construct(
        private readonly OutboxRelayFactory $relays,
        private readonly RabbitMqConfig $rabbitMq,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Continua a pubblicare i batch disponibili.')
            ->addOption('poll-seconds', null, InputOption::VALUE_REQUIRED, 'Attesa quando non ci sono messaggi.', '2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->rabbitMq->enabled) {
            $output->writeln('<error>Il relay RabbitMQ è disabilitato tramite RABBITMQ_ENABLED.</error>');

            return self::FAILURE;
        }

        $watch = (bool) $input->getOption('watch');
        $pollSeconds = $this->pollSeconds($input, $output);
        if ($pollSeconds === null) {
            return self::INVALID;
        }

        $relay = $this->relays->create();
        do {
            $report = $relay->runOnce();
            if (!$watch || $report->recovered + $report->claimed + $report->dead > 0) {
                $output->writeln(sprintf(
                    'recovered=%d claimed=%d published=%d retried=%d dead=%d',
                    $report->recovered,
                    $report->claimed,
                    $report->published,
                    $report->retried,
                    $report->dead,
                ));
            }

            if ($watch && $report->claimed === 0) {
                usleep($pollSeconds * 1_000_000);
            }
        } while ($watch);

        return $report->dead === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function pollSeconds(InputInterface $input, OutputInterface $output): ?int
    {
        $value = $input->getOption('poll-seconds');
        if (!is_string($value) || !ctype_digit($value)) {
            $output->writeln('<error>--poll-seconds deve essere un intero tra 1 e 60.</error>');

            return null;
        }

        $seconds = (int) $value;
        if ($seconds < 1 || $seconds > 60) {
            $output->writeln('<error>--poll-seconds deve essere un intero tra 1 e 60.</error>');

            return null;
        }

        return $seconds;
    }
}
