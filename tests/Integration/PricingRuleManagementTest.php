<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\UserRepository;
use Hapa\Modules\Catalog\Application\PricingRuleService;
use Hapa\Modules\Catalog\Application\MarketplaceOfferRecalculator;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class PricingRuleManagementTest extends TestCase
{
    private PDO $pdo;
    private PricingRuleService $rules;
    private UserIdentity $actor;
    private ?int $catalogId = null;
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
            $this->rules = new PricingRuleService(
                $connections,
                $clock,
                new MarketplaceOfferRecalculator(new PriceCalculator(), $clock),
            );
            $catalog = $this->pdo->prepare(<<<'SQL'
INSERT INTO commercial_catalogs (
    code, name, description, enabled, created_by, created_at, updated_at
) VALUES (
    :code, 'Catalogo prezzi test', 'Perimetro isolato dei test prezzi', TRUE, :created_by, NOW(), NOW()
)
RETURNING id
SQL);
            $catalog->execute([
                'code' => 'pricing-test-' . bin2hex(random_bytes(6)),
                'created_by' => $this->actor->id,
            ]);
            $this->catalogId = (int) $catalog->fetchColumn();
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
        if ($this->catalogId !== null) {
            $this->pdo->prepare('DELETE FROM commercial_catalogs WHERE id = :id')->execute(['id' => $this->catalogId]);
        }
        $this->pdo->prepare('DELETE FROM app_users WHERE id = :id')->execute(['id' => $this->actor->id]);
    }

    public function testCreateUpdateAndRetireAreVersionedAndAudited(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $this->ruleId = $this->rules->create([
            'commercial_catalog_id' => $this->catalogId,
            'code' => 'global-' . $suffix,
            'name' => 'Ricarico globale test',
            'scope' => 'global',
            'adjustment_type' => 'percentage',
            'adjustment_value' => 1500,
            'currency' => 'EUR',
            'priority' => 100,
            'match_field' => 'format',
            'match_operator' => 'equals',
            'match_value' => 'CD',
            'enabled' => true,
        ], $this->actor, 'pricing-create');

        $this->rules->update($this->ruleId, 1, [
            'commercial_catalog_id' => $this->catalogId,
            'code' => 'global-' . $suffix,
            'name' => 'Ricarico globale aggiornato',
            'scope' => 'global',
            'adjustment_type' => 'fixed_amount',
            'adjustment_value' => 500,
            'currency' => 'EUR',
            'priority' => 200,
            'match_field' => 'supplier_id',
            'match_operator' => 'equals',
            'match_value' => 'aec',
            'enabled' => true,
        ], $this->actor, 'pricing-update');
        $this->rules->retire($this->ruleId, 2, $this->actor, 'pricing-retire');

        $statement = $this->pdo->prepare('SELECT version, enabled, retired_at, match_field, match_operator, match_value FROM pricing_rules WHERE id = :id');
        $statement->execute(['id' => $this->ruleId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(3, (int) $row['version']);
        self::assertFalse(filter_var($row['enabled'], FILTER_VALIDATE_BOOL));
        self::assertNotNull($row['retired_at']);
        self::assertSame('supplier_id', $row['match_field']);
        self::assertSame('equals', $row['match_operator']);
        self::assertSame('aec', $row['match_value']);

        $history = $this->pdo->prepare('SELECT COUNT(*) FROM pricing_rule_history WHERE pricing_rule_id = :id');
        $history->execute(['id' => $this->ruleId]);
        self::assertSame(3, (int) $history->fetchColumn());
        $audit = $this->pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'pricing_rule' AND entity_id = :id");
        $audit->execute(['id' => (string) $this->ruleId]);
        self::assertSame(3, (int) $audit->fetchColumn());
    }
}
