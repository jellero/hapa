<?php

declare(strict_types=1);

namespace Hapa\Tests\Integration;

use DateTimeImmutable;
use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Configuration\ConfigurationLoader;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Catalog\Application\MarketplaceOfferRecalculator;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Space\Application\SpaceCatalogObservationHandler;
use Hapa\Modules\Space\Domain\SpaceCatalogIngestionOutcome;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SpaceCatalogObservationIngestionTest extends TestCase
{
    private PDO $pdo;
    private SpaceCatalogObservationHandler $handler;

    protected function setUp(): void
    {
        try {
            $this->pdo = (new ConnectionFactory(ConfigurationLoader::load()->database))->create();
            $this->pdo->beginTransaction();
            $this->handler = new SpaceCatalogObservationHandler(
                $this->pdo,
                new PdoTransactionManager($this->pdo),
                new MarketplaceOfferRecalculator(new PriceCalculator(), new SystemClock()),
            );
        } catch (Throwable $exception) {
            self::markTestSkipped('PostgreSQL di test non disponibile: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function testANewSpaceItemCreatesAnInactiveProductPendingReview(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $message = $this->message($suffix, [
            'external_item_id' => 'SPACE-' . $suffix,
            'supplier_sku' => 'SKU-' . $suffix,
            'ean' => '978' . random_int(1000000000, 9999999999),
            'name' => 'Nuovo prodotto Space',
            'description' => 'Descrizione da verificare',
        ]);

        $result = $this->handler->handle($message);
        self::assertSame(SpaceCatalogIngestionOutcome::CreatedPendingReview, $result->outcome);
        self::assertNotNull($result->catalogItemId);

        $statement = $this->pdo->prepare(<<<'SQL'
SELECT item.sku, item.name, item.active, item.onboarding_status, item.sellable_quantity,
       offer.external_item_id, offer.purchase_cost_minor, offer.available_quantity
FROM catalog_items AS item
JOIN supplier_catalog_items AS offer ON offer.catalog_item_id = item.id
WHERE item.id = :id
SQL);
        $statement->execute(['id' => $result->catalogItemId]);
        $row = $statement->fetch();
        self::assertIsArray($row);
        self::assertSame('SKU-' . $suffix, $row['sku']);
        self::assertSame('Nuovo prodotto Space', $row['name']);
        self::assertFalse((bool) $row['active']);
        self::assertSame('pending_review', $row['onboarding_status']);
        self::assertSame(1299, (int) $row['purchase_cost_minor']);
        self::assertSame(12, (int) $row['available_quantity']);
        self::assertSame(12, (int) $row['sellable_quantity']);
        $offers = $this->pdo->query(
            'SELECT COUNT(*) FROM marketplace_offers WHERE catalog_item_id = ' . (int) $result->catalogItemId,
        );
        self::assertNotFalse($offers);
        self::assertGreaterThanOrEqual(1, (int) $offers->fetchColumn());

        $duplicate = $this->handler->handle($message);
        self::assertSame(SpaceCatalogIngestionOutcome::Duplicate, $duplicate->outcome);
        self::assertSame($result->catalogItemId, $duplicate->catalogItemId);
        self::assertSame(1, $this->countBy('supplier_catalog_observations', 'message_id', $message->messageId));
    }

    public function testItLinksByEanWithoutOverwritingAnApprovedProduct(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $ean = '978' . random_int(1000000000, 9999999999);
        $catalogItemId = $this->insertCatalogItem('HAPA-' . $suffix, $ean, 'Nome approvato');
        $message = $this->message($suffix, [
            'external_item_id' => 'SPACE-' . $suffix,
            'supplier_sku' => 'SPACE-SKU-' . $suffix,
            'ean' => $ean,
            'name' => 'Nome osservato da Space',
        ]);

        $result = $this->handler->handle($message);

        self::assertSame(SpaceCatalogIngestionOutcome::LinkedExisting, $result->outcome);
        self::assertSame($catalogItemId, $result->catalogItemId);
        self::assertSame('Nome approvato', $this->value(
            'SELECT name FROM catalog_items WHERE id = ' . $catalogItemId,
        ));
        self::assertSame('approved', $this->value(
            'SELECT onboarding_status FROM catalog_items WHERE id = ' . $catalogItemId,
        ));
    }

    public function testConflictingEanAndSkuGoToManualReview(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $ean = '978' . random_int(1000000000, 9999999999);
        $this->insertCatalogItem('EAN-' . $suffix, $ean, 'Prodotto EAN');
        $this->insertCatalogItem('SKU-' . $suffix, null, 'Prodotto SKU');
        $message = $this->message($suffix, [
            'external_item_id' => 'SPACE-' . $suffix,
            'supplier_sku' => 'SKU-' . $suffix,
            'ean' => $ean,
        ]);

        $result = $this->handler->handle($message);

        self::assertSame(SpaceCatalogIngestionOutcome::IdentityConflict, $result->outcome);
        self::assertNull($result->catalogItemId);
        self::assertSame('manual_review', $this->value(
            'SELECT status FROM supplier_catalog_observations WHERE id = ' . $result->observationId,
        ));
        self::assertSame(0, $this->countBy(
            'supplier_catalog_items',
            'external_item_id',
            'SPACE-' . $suffix,
        ));
    }

    public function testAnOlderObservationDoesNotRollBackCostOrAvailability(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $newer = $this->message($suffix . '-new', [
            'external_item_id' => 'SPACE-' . $suffix,
            'supplier_sku' => 'SKU-' . $suffix,
            'purchase_cost_minor' => 1800,
            'available_quantity' => 20,
            'source_version' => 'version-2',
            'observed_at' => '2026-07-17T11:00:00+00:00',
        ]);
        $older = $this->message($suffix . '-old', [
            'external_item_id' => 'SPACE-' . $suffix,
            'supplier_sku' => 'SKU-' . $suffix,
            'purchase_cost_minor' => 900,
            'available_quantity' => 2,
            'source_version' => 'version-1',
            'observed_at' => '2026-07-17T10:00:00+00:00',
        ]);

        $this->handler->handle($newer);
        $result = $this->handler->handle($older);

        self::assertSame(SpaceCatalogIngestionOutcome::IgnoredStale, $result->outcome);
        $statement = $this->pdo->prepare(<<<'SQL'
SELECT purchase_cost_minor, available_quantity
FROM supplier_catalog_items
WHERE external_item_id = :external_item_id
SQL);
        $statement->execute(['external_item_id' => 'SPACE-' . $suffix]);
        $offer = $statement->fetch();
        self::assertIsArray($offer);
        self::assertSame(1800, (int) $offer['purchase_cost_minor']);
        self::assertSame(20, (int) $offer['available_quantity']);
    }

    /** @param array<string, int|string|null> $overrides */
    private function message(string $suffix, array $overrides = []): MessageEnvelope
    {
        $payload = [
            'supplier' => 'space',
            'external_item_id' => 'SPACE-' . $suffix,
            'supplier_sku' => 'SKU-' . $suffix,
            'ean' => null,
            'name' => null,
            'description' => null,
            'purchase_cost_minor' => 1299,
            'currency' => 'EUR',
            'available_quantity' => 12,
            'source_version' => 'version-' . $suffix,
            'observed_at' => '2026-07-17T10:00:00+00:00',
            ...$overrides,
        ];

        return new MessageEnvelope(
            'message-' . $suffix,
            'space.catalog.item.observed',
            1,
            new DateTimeImmutable((string) $payload['observed_at']),
            'correlation-' . $suffix,
            null,
            $payload,
        );
    }

    private function insertCatalogItem(string $sku, ?string $ean, string $name): int
    {
        $statement = $this->pdo->prepare(<<<'SQL'
INSERT INTO catalog_items (sku, ean, name, currency, active, onboarding_status)
VALUES (:sku, :ean, :name, 'EUR', TRUE, 'approved')
RETURNING id
SQL);
        $statement->execute(['sku' => $sku, 'ean' => $ean, 'name' => $name]);

        return (int) $statement->fetchColumn();
    }

    private function countBy(string $table, string $column, string $value): int
    {
        self::assertMatchesRegularExpression('/^[a-z_]+$/D', $table);
        self::assertMatchesRegularExpression('/^[a-z_]+$/D', $column);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT COUNT(*) FROM %s WHERE %s = :value',
            $table,
            $column,
        ));
        $statement->execute(['value' => $value]);

        return (int) $statement->fetchColumn();
    }

    private function value(string $sql): mixed
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        return $statement->fetchColumn();
    }
}
