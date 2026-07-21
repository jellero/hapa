<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use Hapa\Core\Messaging\InboxConsumerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'inbox:consume',
    description: 'Consuma un messaggio RabbitMQ nella inbox idempotente HAPA.',
)]
final class InboxConsumeCommand extends Command
{
    public function __construct(
        private readonly InboxConsumerFactory $consumers,
        private readonly RabbitMqConsumerConfig $configuration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Continua a consumare messaggi.')
            ->addOption('poll-seconds', null, InputOption::VALUE_REQUIRED, 'Attesa quando la coda è vuota.', '2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->configuration->enabled) {
            $output->writeln(
                '<error>Il consumer RabbitMQ HAPA è disabilitato tramite RABBITMQ_CONSUMER_ENABLED.</error>',
            );

            return self::FAILURE;
        }

        $watch = (bool) $input->getOption('watch');
        $pollSeconds = $this->pollSeconds($input, $output);
        if ($pollSeconds === null) {
            return self::INVALID;
        }

        $this->consume($watch, $pollSeconds, $output);

        return self::SUCCESS;
    }

    private function consume(bool $watch, int $pollSeconds, OutputInterface $output): void
    {
        $consumer = $this->consumers->create();
        do {
            $report = $consumer->runOnce();
            if (!$watch || $report->consumed) {
                $this->writeReport($report, $output);
            }

            if ($watch && !$report->consumed) {
                usleep($pollSeconds * 1_000_000);
            }
        } while ($watch);
    }

    private function writeReport(\Hapa\Core\Messaging\InboxConsumerReport $report, OutputInterface $output): void
    {
        $output->writeln(sprintf(
            'consumed=%d processed=%d duplicate=%d',
            $report->consumed ? 1 : 0,
            $report->processed ? 1 : 0,
            $report->duplicate ? 1 : 0,
        ));
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
