<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Modules\Catalog\Application\CatalogPublicationRuleService;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CatalogPublicationRuleServiceTest extends TestCase
{
    private PDO $pdo;
    private CatalogPublicationRuleService $service;
    private UserIdentity $actor;
    private ?int $catalogId = null;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $this->service = new CatalogPublicationRuleService($connections, new SystemClock());
            $actor = $this->pdo->query(
                "SELECT id, email, display_name, role FROM app_users WHERE status = 'active' ORDER BY created_at LIMIT 1",
            );
            $row = $actor === false ? false : $actor->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                self::markTestSkipped('Utente HAPA attivo non disponibile.');
            }
            $this->actor = new UserIdentity(
                (string) $row['id'],
                (string) $row['email'],
                (string) $row['display_name'],
                (string) $row['role'],
            );
            $catalog = $this->pdo->prepare(<<<'SQL'
INSERT INTO commercial_catalogs (code, name, enabled, created_by, created_at, updated_at)
VALUES (:code, 'Catalogo filtri test', TRUE, :actor_id, NOW(), NOW())
RETURNING id
SQL);
            $catalog->execute([
                'code' => 'publication-test-' . bin2hex(random_bytes(6)),
                'actor_id' => $this->actor->id,
            ]);
            $this->catalogId = (int) $catalog->fetchColumn();
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo) || $this->catalogId === null) {
            return;
        }
        $this->pdo->prepare(
            'DELETE FROM catalog_publication_rules WHERE commercial_catalog_id = :catalog_id',
        )->execute(['catalog_id' => $this->catalogId]);
        $this->pdo->prepare(
            'DELETE FROM commercial_catalogs WHERE id = :catalog_id',
        )->execute(['catalog_id' => $this->catalogId]);
    }

    public function testItCreatesARuleForEveryMarketplaceInTheCatalog(): void
    {
        $this->service->create([
            'commercial_catalog_id' => (string) $this->catalogId,
            'code' => '',
            'name' => '',
            'marketplace_id' => '',
            'action' => 'exclude',
            'field' => 'sku',
            'operator' => 'contains',
            'match_value' => 'A25',
            'priority' => '100',
        ], $this->actor);

        $statement = $this->pdo->prepare(
            'SELECT code, name, marketplace_id, action, field, operator, match_value '
            . 'FROM catalog_publication_rules WHERE commercial_catalog_id = :catalog_id',
        );
        $statement->execute(['catalog_id' => $this->catalogId]);
        $rule = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($rule);
        self::assertStringStartsWith('exclude-sku-', $rule['code']);
        self::assertSame('Escludi sku contains A25', $rule['name']);
        self::assertNull($rule['marketplace_id']);
        self::assertSame('exclude', $rule['action']);
        self::assertSame('sku', $rule['field']);
        self::assertSame('contains', $rule['operator']);
        self::assertSame('A25', $rule['match_value']);
    }
}
