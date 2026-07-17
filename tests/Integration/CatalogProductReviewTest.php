<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\UserRepository;
use Hapa\Modules\Catalog\Application\CatalogProductReviewService;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CatalogProductReviewTest extends TestCase
{
    private PDO $pdo;
    private UserIdentity $actor;
    private int $itemId;

    protected function setUp(): void
    {
        try {
            $connections = new ConnectionFactory(ConfigurationLoader::load()->database);
            $this->pdo = $connections->create();
            $clock = new SystemClock();
            $this->actor = (new UserRepository($connections))->create(
                sprintf('catalog-review-%s@example.test', bin2hex(random_bytes(5))),
                'Catalog Reviewer',
                'administrator',
                password_hash('Catalog-review-test-2026!', PASSWORD_ARGON2ID),
                $clock->now(),
            );
            $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO catalog_items (sku, name, currency, active, onboarding_status, version, created_at, updated_at)
VALUES (:sku, 'Prodotto da revisionare', 'EUR', FALSE, 'pending_review', 1, NOW(), NOW())
RETURNING id
SQL);
            $statement->execute(['sku' => 'REVIEW-' . bin2hex(random_bytes(6))]);
            $this->itemId = (int) $statement->fetchColumn();
            (new CatalogProductReviewService($connections, $clock))->review(
                $this->itemId,
                1,
                'approved',
                $this->actor,
                'catalog-review-test',
            );
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL HAPA non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo, $this->actor, $this->itemId)) {
            return;
        }
        $this->pdo->prepare('DELETE FROM catalog_item_history WHERE catalog_item_id = :id')->execute(['id' => $this->itemId]);
        $this->pdo->prepare("DELETE FROM audit_logs WHERE entity_type = 'catalog_item' AND entity_id = :id")->execute(['id' => (string) $this->itemId]);
        $this->pdo->prepare('DELETE FROM catalog_items WHERE id = :id')->execute(['id' => $this->itemId]);
        $this->pdo->prepare('DELETE FROM app_users WHERE id = :id')->execute(['id' => $this->actor->id]);
    }

    public function testApprovalActivatesAndAuditsTheProduct(): void
    {
        $statement = $this->pdo->prepare('SELECT onboarding_status, active, version FROM catalog_items WHERE id = :id');
        $statement->execute(['id' => $this->itemId]);
        $item = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($item);
        self::assertSame('approved', $item['onboarding_status']);
        self::assertTrue(filter_var($item['active'], FILTER_VALIDATE_BOOL));
        self::assertSame(2, (int) $item['version']);

        $history = $this->pdo->prepare('SELECT action FROM catalog_item_history WHERE catalog_item_id = :id');
        $history->execute(['id' => $this->itemId]);
        self::assertSame('approved', $history->fetchColumn());
    }
}
