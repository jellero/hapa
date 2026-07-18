<?php

declare(strict_types=1);

namespace Hapa\Modules\Procurement\Application;

use Hapa\Core\Ui\SpacePurchaseManagement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'space:purchases:backfill',
    description: 'Genera gli acquisti Space mancanti o nuovamente risolvibili per gli ordini marketplace.',
)]
final class SpacePurchaseBackfillCommand extends Command
{
    public function __construct(private readonly SpacePurchaseManagement $purchases)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Numero massimo di ordini da esaminare.', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getOption('limit');
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || (int) $value > 2000) {
            $output->writeln('<error>--limit deve essere un intero tra 1 e 2000.</error>');

            return self::INVALID;
        }

        $report = $this->purchases->generateOutstanding('space-backfill-' . bin2hex(random_bytes(12)), (int) $value);
        $output->writeln(sprintf(
            'examined=%d generated=%d manual_review=%d failed=%d',
            $report['examined'],
            $report['generated'],
            $report['manual_review'],
            $report['failed'],
        ));

        return $report['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
