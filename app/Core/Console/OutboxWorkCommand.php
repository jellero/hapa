<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Database\ConnectionFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'outbox:work', description: 'Avvia il worker infrastrutturale della transactional outbox.')]
final class OutboxWorkCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Secondi tra due cicli', '2')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Esegue un solo ciclo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sleep = max(1, (int) $input->getOption('sleep'));
        $once = (bool) $input->getOption('once');

        do {
            $pdo = (new ConnectionFactory())->create();
            $pdo->query('SELECT 1');
            $output->writeln(sprintf('[%s] worker outbox attivo', (new \DateTimeImmutable())->format(DATE_ATOM)));

            if (!$once) {
                sleep($sleep);
            }
        } while (!$once);

        return Command::SUCCESS;
    }
}
