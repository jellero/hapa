<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Configuration\RabbitMqConfig;
use Hapa\Core\Outbox\OutboxRelay;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'outbox:relay',
    description: 'Pubblica su RabbitMQ un batch della transactional outbox HAPA.',
)]
final class OutboxRelayCommand extends Command
{
    public function __construct(
        private readonly OutboxRelay $relay,
        private readonly RabbitMqConfig $rabbitMq,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->rabbitMq->enabled) {
            $output->writeln('<error>Il relay RabbitMQ è disabilitato tramite RABBITMQ_ENABLED.</error>');

            return self::FAILURE;
        }

        $report = $this->relay->runOnce();
        $output->writeln(sprintf(
            'recovered=%d claimed=%d published=%d retried=%d dead=%d',
            $report->recovered,
            $report->claimed,
            $report->published,
            $report->retried,
            $report->dead,
        ));

        return $report->dead === 0 ? self::SUCCESS : self::FAILURE;
    }
}
