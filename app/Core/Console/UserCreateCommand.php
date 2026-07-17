<?php

declare(strict_types=1);

namespace Hapa\Core\Console;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Security\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'security:user:create', description: 'Crea un utente operativo HAPA senza salvare password in chiaro.')]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email di accesso')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nome visualizzato')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'administrator, operator o viewer', 'administrator')
            ->addOption('password-env', null, InputOption::VALUE_REQUIRED, 'Variabile ambiente contenente la password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim((string) $input->getOption('email'));
        $name = trim((string) $input->getOption('name'));
        $role = (string) $input->getOption('role');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || !in_array($role, ['administrator', 'operator', 'viewer'], true)) {
            $output->writeln('<error>Email, nome o ruolo non validi.</error>');

            return Command::INVALID;
        }

        $passwordEnvironment = trim((string) $input->getOption('password-env'));
        $generated = $passwordEnvironment === '';
        $password = $generated ? self::password() : getenv($passwordEnvironment);
        if (!is_string($password) || strlen($password) < 14) {
            $output->writeln('<error>La password deve contenere almeno 14 caratteri.</error>');

            return Command::INVALID;
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $user = $this->users->create($email, $name, $role, $hash, $this->clock->now());
        $output->writeln(sprintf('<info>Utente %s creato con ruolo %s.</info>', $user->email, $user->role));
        if ($generated) {
            $output->writeln(sprintf('<comment>Password generata (mostrata una sola volta): %s</comment>', $password));
        }

        return Command::SUCCESS;
    }

    private static function password(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
}
