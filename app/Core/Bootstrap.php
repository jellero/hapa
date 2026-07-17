<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Composition\ContainerFactory;
use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Console\InboxConsumeCommand;
use Hapa\Core\Console\OutboxRelayCommand;
use Hapa\Core\Console\SystemCheckCommand;
use Hapa\Core\Console\UserCreateCommand;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

final readonly class Bootstrap
{
    private function __construct(
        public ApplicationConfig $application,
        private ContainerBuilder $container,
    ) {
    }

    public static function initialize(string $basePath): self
    {
        if (is_file($basePath . '/.env')) {
            (new Dotenv())->usePutenv()->loadEnv($basePath . '/.env');
        }

        $configuration = ConfigurationLoader::load();
        date_default_timezone_set($configuration->application->timezone);

        if ($configuration->proxy->trustedProxies !== []) {
            Request::setTrustedProxies(
                $configuration->proxy->trustedProxies,
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
            );
        }

        return new self(
            $configuration->application,
            (new ContainerFactory())->create($basePath, $configuration),
        );
    }

    public function kernel(): Kernel
    {
        $kernel = $this->container->get(Kernel::class);
        if (!$kernel instanceof Kernel) {
            throw new RuntimeException('Il container non ha prodotto il Kernel applicativo.');
        }

        return $kernel;
    }

    public function systemCheckCommand(): SystemCheckCommand
    {
        $command = $this->container->get(SystemCheckCommand::class);
        if (!$command instanceof SystemCheckCommand) {
            throw new RuntimeException('Il container non ha prodotto il comando system:check.');
        }

        return $command;
    }

    public function outboxRelayCommand(): OutboxRelayCommand
    {
        $command = $this->container->get(OutboxRelayCommand::class);
        if (!$command instanceof OutboxRelayCommand) {
            throw new RuntimeException('Il container non ha prodotto il comando outbox:relay.');
        }

        return $command;
    }

    public function inboxConsumeCommand(): InboxConsumeCommand
    {
        $command = $this->container->get(InboxConsumeCommand::class);
        if (!$command instanceof InboxConsumeCommand) {
            throw new RuntimeException('Il container non ha prodotto il comando inbox:consume.');
        }

        return $command;
    }

    public function userCreateCommand(): UserCreateCommand
    {
        $command = $this->container->get(UserCreateCommand::class);
        if (!$command instanceof UserCreateCommand) {
            throw new RuntimeException('Il container non ha prodotto il comando security:user:create.');
        }

        return $command;
    }

    public function container(): ContainerBuilder
    {
        return $this->container;
    }
}
