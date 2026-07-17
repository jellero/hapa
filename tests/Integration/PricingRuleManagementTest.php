<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\UserRepository;
use Hapa\Modules\Catalog\Application\PricingRuleService;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class PricingRuleManagementTest extends TestCase
{
    private PDO $pdo;
    private PricingRuleService $rules;
    private UserIdentity $actor;
    private ?int $ruleId = null;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $clock = new SystemClock();
            $this->actor = (new UserRepository($connections))->create(
                sprintf('pricing-%s@example.test', bin2hex(random_bytes(6))),
                'Pricing Administrator',
                'administrator',
                password_hash('Pricing-test-password-2026!', PASSWORD_ARGON2ID),
                $clock->now(),
            );
            $this->rules = new PricingRuleService($connections, $clock);
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo, $this->actor)) {
            return;
        }
        if ($this->ruleId !== null) {
            $this->pdo->prepare('DELETE FROM pricing_rule_history WHERE pricing_rule_id = :id')->execute(['id' => $this->ruleId]);
            $this->pdo->prepare("DELETE FROM audit_logs WHERE entity_type = 'pricing_rule' AND entity_id = :id")->execute(['id' => (string) $this->ruleId]);
            $this->pdo->prepare('DELETE FROM pricing_rules WHERE id = :id')->execute(['id' => $this->ruleId]);
        }
        $this->pdo->prepare('DELETE FROM app_users WHERE id = :id')->execute(['id' => $this->actor->id]);
    }

    public function testCreateUpdateAndRetireAreVersionedAndAudited(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $this->ruleId = $this->rules->create([
            'code' => 'global-' . $suffix,
            'name' => 'Ricarico globale test',
            'scope' => 'global',
            'adjustment_type' => 'percentage',
            'adjustment_value' => 1500,
            'currency' => 'EUR',
            'priority' => 100,
            'enabled' => true,
        ], $this->actor, 'pricing-create');

        $this->rules->update($this->ruleId, 1, [
            'code' => 'global-' . $suffix,
            'name' => 'Ricarico globale aggiornato',
            'scope' => 'global',
            'adjustment_type' => 'fixed_amount',
            'adjustment_value' => 500,
            'currency' => 'EUR',
            'priority' => 200,
            'enabled' => true,
        ], $this->actor, 'pricing-update');
        $this->rules->retire($this->ruleId, 2, $this->actor, 'pricing-retire');

        $statement = $this->pdo->prepare('SELECT version, enabled, retired_at FROM pricing_rules WHERE id = :id');
        $statement->execute(['id' => $this->ruleId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(3, (int) $row['version']);
        self::assertFalse(filter_var($row['enabled'], FILTER_VALIDATE_BOOL));
        self::assertNotNull($row['retired_at']);

        $history = $this->pdo->prepare('SELECT COUNT(*) FROM pricing_rule_history WHERE pricing_rule_id = :id');
        $history->execute(['id' => $this->ruleId]);
        self::assertSame(3, (int) $history->fetchColumn());
        $audit = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'pricing_rule' AND entity_id = :id");
        $audit->execute(['id' => (string) $this->ruleId]);
        self::assertSame(3, (int) $audit->fetchColumn());
    }
}
