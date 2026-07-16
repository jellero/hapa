<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use Hapa\Core\Messaging\InboxConsumerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->configuration->enabled) {
            $output->writeln(
                '<error>Il consumer RabbitMQ HAPA è disabilitato tramite RABBITMQ_CONSUMER_ENABLED.</error>',
            );

            return self::FAILURE;
        }

        $report = $this->consumers->create()->runOnce();
        $output->writeln(sprintf(
            'consumed=%d processed=%d duplicate=%d',
            $report->consumed ? 1 : 0,
            $report->processed ? 1 : 0,
            $report->duplicate ? 1 : 0,
        ));

        return self::SUCCESS;
    }
}
