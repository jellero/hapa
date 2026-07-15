<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Health\ReadinessCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'system:check', description: 'Verifica PostgreSQL e Redis senza esporre credenziali.')]
final class SystemCheckCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = (new ReadinessCheck(new ConnectionFactory()))->check();

        foreach ($result['components'] as $component => $ready) {
            $output->writeln(sprintf('%s: %s', $component, $ready ? 'OK' : 'KO'));
        }

        return $result['ready'] ? Command::SUCCESS : Command::FAILURE;
    }
}
