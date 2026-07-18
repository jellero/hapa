<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Integration\IntegrationAccountRepository;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class IntegrationAccountLifecycleTest extends TestCase
{
    private PDO $pdo;
    private IntegrationAccountRepository $accounts;
    private UserIdentity $actor;
    private ?int $accountId = null;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $clock = new SystemClock();
            $this->actor = (new UserRepository($connections))->create(
                sprintf('integration-%s@example.test', bin2hex(random_bytes(6))),
                'Integration Administrator',
                'administrator',
                password_hash('Integration-test-password-2026!', PASSWORD_ARGON2ID),
                $clock->now(),
            );
            $this->accounts = new IntegrationAccountRepository($connections, $clock);
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo, $this->actor)) {
            return;
        }
        if ($this->accountId !== null) {
            $this->pdo->prepare("DELETE FROM audit_logs WHERE entity_type = 'integration_account' AND entity_id = :id")->execute(['id' => (string) $this->accountId]);
            $this->pdo->prepare('DELETE FROM integration_account_history WHERE integration_account_id = :id')->execute(['id' => $this->accountId]);
            $this->pdo->prepare('DELETE FROM integration_account_settings WHERE integration_account_id = :id')->execute(['id' => $this->accountId]);
            $this->pdo->prepare('DELETE FROM integration_account_capabilities WHERE integration_account_id = :id')->execute(['id' => $this->accountId]);
            $this->pdo->prepare('DELETE FROM integration_accounts WHERE id = :id')->execute(['id' => $this->accountId]);
        }
        $this->pdo->prepare('DELETE FROM app_users WHERE id = :id')->execute(['id' => $this->actor->id]);
    }

    public function testActivationRequiresSecretsConnectionTestAndSynchronizedConfiguration(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $this->accountId = $this->accounts->create([
            'provider' => 'sellrapido',
            'code' => 'sellrapido-' . $suffix,
            'display_name' => 'SellRapido test',
            'environment' => 'sandbox',
            'description' => null,
            'capabilities' => ['orders.read'],
            'settings' => ['base_url' => 'https://example.test'],
        ], $this->actor, 'integration-create');
        $this->accounts->recordAutomationConfigurationStatus($this->accountId, [
            'status' => 'applied', 'configuration_version' => 1, 'applied_at' => gmdate(DATE_ATOM),
        ], $this->actor, 'integration-sync');
        $this->accounts->recordSecretStatus($this->accountId, [
            'status' => 'configured', 'secret_version' => 1, 'rotated_at' => gmdate(DATE_ATOM),
        ], $this->actor, 'integration-secret');

        try {
            $this->accounts->changeDesiredStatus($this->accountId, 1, 'pilot', $this->actor, 'integration-pilot-denied');
            self::fail('Il pilot senza test connessione doveva essere rifiutato.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('test connessione', $exception->getMessage());
        }

        $this->accounts->recordConnectionTest($this->accountId, [
            'status' => 'passed',
            'tested_at' => gmdate(DATE_ATOM),
            'token_expires_at' => gmdate(DATE_ATOM, time() + 900),
        ], $this->actor, 'integration-connection-test');
        $this->accounts->changeDesiredStatus($this->accountId, 1, 'pilot', $this->actor, 'integration-pilot');
        $account = $this->accounts->find($this->accountId);
        self::assertSame('pilot', $account['desired_status']);
        self::assertSame(2, $account['configuration_version']);
        self::assertSame(1, $account['automation_configuration_version']);

        $this->expectException(RuntimeException::class);
        $this->accounts->changeDesiredStatus($this->accountId, 2, 'active', $this->actor, 'integration-active-denied');
    }
}
