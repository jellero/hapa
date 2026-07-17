<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Application;

use Hapa\Core\Configuration\RabbitMqConfig;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Space\Infrastructure\Messaging\RabbitMqSpaceCatalogConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'space:catalog:consume',
    description: 'Consuma le osservazioni catalogo Space e crea o aggiorna i prodotti HAPA.',
)]
final class SpaceCatalogConsumeCommand extends Command
{
    private bool $running = true;

    public function __construct(
        private readonly RabbitMqSpaceCatalogConsumer $consumer,
        private readonly SpaceCatalogObservationHandler $handler,
        private readonly RabbitMqConfig $rabbitMq,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Consuma al massimo un messaggio e termina.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->rabbitMq->enabled) {
            $output->writeln('<error>RabbitMQ è disabilitato tramite RABBITMQ_ENABLED.</error>');

            return self::FAILURE;
        }

        $once = (bool) $input->getOption('once');
        $this->installSignalHandlers();

        try {
            do {
                try {
                    $consumed = $this->consumer->consumeOne(
                        function (MessageEnvelope $message) use ($output): void {
                            $result = $this->handler->handle($message);
                            $output->writeln(json_encode([
                                'message_id' => $message->messageId,
                                'observation_id' => $result->observationId,
                                'catalog_item_id' => $result->catalogItemId,
                                'outcome' => $result->outcome->value,
                                'reason' => $result->reason,
                            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
                        },
                    );
                } catch (Throwable $exception) {
                    $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
                    if ($once) {
                        return self::FAILURE;
                    }
                    $consumed = false;
                }

                if (!$once && $this->running && !$consumed) {
                    sleep(2);
                }
            } while (!$once && $this->running);
        } finally {
            $this->consumer->close();
        }

        return self::SUCCESS;
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function (): void {
            $this->running = false;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->running = false;
        });
    }
}
