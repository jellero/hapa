<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Ui\PricingPreview;
use Hapa\Modules\Catalog\Contract\Money;
use Hapa\Modules\Catalog\Domain\PriceAdjustmentType;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Catalog\Domain\PricingRule;
use Hapa\Modules\Catalog\Domain\PricingRuleScope;
use PDO;
use Throwable;

final class PricingPreviewService implements PricingPreview
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly ConnectionFactory $connections,
        private readonly PriceCalculator $calculator,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return array<int, list<array<string, mixed>>>
     */
    public function forProducts(array $products, ?int $commercialCatalogId = null): array
    {
        $marketplaces = $this->marketplaces($commercialCatalogId);
        $rules = $this->rules($commercialCatalogId);
        $publicationRules = $this->publicationRules($commercialCatalogId);
        $productIds = array_values(array_unique(array_filter(array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $products,
        ), static fn (int $id): bool => $id > 0)));
        $savedOffers = $this->savedOffers($productIds);
        $previews = [];
        foreach ($products as $product) {
            $id = (int) ($product['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $sellableQuantity = array_key_exists('sellable_quantity', $product)
                ? (int) $product['sellable_quantity']
                : max(
                    0,
                    (int) ($product['available_quantity'] ?? 0)
                    + (int) ($product['backorder_quantity'] ?? 0)
                    - (int) ($product['safety_stock'] ?? 0),
                );
            $previews[$id] = [];
            $cost = $product['purchase_cost_minor'] ?? null;
            $currency = $product['currency'] ?? null;
            if (!is_int($cost) || !is_string($currency)) {
                continue;
            }
            $basePrice = new Money($cost, $currency);
            $sku = (string) ($product['sku'] ?? '');
            foreach ($marketplaces as $marketplace) {
                $preview = [
                    'marketplace_id' => $marketplace['id'],
                    'marketplace_code' => $marketplace['code'],
                    'marketplace_name' => $marketplace['name'],
                    'marketplace_status' => $marketplace['business_status'],
                    'technical_account_count' => $marketplace['technical_account_count'],
                    'base_price_minor' => $cost,
                    'selling_price_minor' => null,
                    'markup_minor' => null,
                    'sellable_quantity' => $sellableQuantity,
                    'currency' => $currency,
                    'applied_rule_code' => null,
                    'publishable' => false,
                    'blockers' => [],
                    'error' => null,
                    'offer_status' => null,
                    'offer_version' => null,
                    'calculated_at' => null,
                ];
                $saved = $savedOffers[$id][(int) $marketplace['id']] ?? null;
                if (is_array($saved)) {
                    $preview['offer_status'] = $saved['status'];
                    $preview['offer_version'] = $saved['source_version'];
                    $preview['calculated_at'] = $saved['calculated_at'];
                }
                try {
                    $calculated = $this->calculator->calculate($basePrice, $marketplace['code'], $sku, $rules);
                    $preview['selling_price_minor'] = $calculated->sellingPrice->minorAmount;
                    $preview['markup_minor'] = $calculated->sellingPrice->minorAmount - $cost;
                    $preview['applied_rule_code'] = $calculated->appliedRuleCode;
                } catch (Throwable $exception) {
                    $preview['error'] = $exception->getMessage();
                }
                $blockers = $this->blockers(
                    $product,
                    $marketplace,
                    $preview,
                    $commercialCatalogId === null
                        || $this->publicationAllows($product, (int) $marketplace['id'], $publicationRules),
                );
                $preview['blockers'] = $blockers;
                $preview['publishable'] = $blockers === [];
                $previews[$id][] = $preview;
            }
        }

        return $previews;
    }

    /** @return list<array{id: int, code: string, name: string, business_status: string, technical_account_count: int}> */
    private function marketplaces(?int $commercialCatalogId): array
    {
        $sql = <<<'SQL'
SELECT marketplace.id, marketplace.code, marketplace.name, marketplace.business_status,
       COUNT(account.id) FILTER (
           WHERE account.technical_enabled
             AND account.status IN ('pilot', 'active')
       ) AS technical_account_count
FROM marketplaces marketplace
LEFT JOIN marketplace_accounts account ON account.marketplace_id = marketplace.id
WHERE marketplace.business_status <> 'retired'
SQL;
        $parameters = [];
        if ($commercialCatalogId !== null) {
            $sql .= <<<'SQL'

  AND EXISTS (
      SELECT 1 FROM commercial_catalog_marketplaces link
      WHERE link.marketplace_id = marketplace.id AND link.commercial_catalog_id = :catalog_id
  )
SQL;
            $parameters['catalog_id'] = $commercialCatalogId;
        }
        $sql .= <<<'SQL'

GROUP BY marketplace.id
ORDER BY CASE marketplace.business_status WHEN 'active' THEN 0 WHEN 'pilot' THEN 1 ELSE 2 END,
         marketplace.name
SQL;
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'business_status' => (string) $row['business_status'],
            'technical_account_count' => (int) $row['technical_account_count'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /**
     * @param list<int> $productIds
     * @return array<int, array<int, array{status: string, source_version: int, calculated_at: string|null}>>
     */
    private function savedOffers(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $placeholders = [];
        foreach ($productIds as $index => $productId) {
            $placeholders['product_' . $index] = $productId;
        }
        $sql = <<<'SQL'
SELECT catalog_item_id, marketplace_id, status, source_version, calculated_at
FROM marketplace_offers
WHERE marketplace_account_id IS NULL
  AND catalog_item_id IN (%s)
SQL;
        $statement = $this->connection()->prepare(sprintf(
            $sql,
            implode(', ', array_map(static fn (string $name): string => ':' . $name, array_keys($placeholders))),
        ));
        foreach ($placeholders as $name => $productId) {
            $statement->bindValue($name, $productId, PDO::PARAM_INT);
        }
        $statement->execute();
        $offers = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offers[(int) $row['catalog_item_id']][(int) $row['marketplace_id']] = [
                'status' => (string) $row['status'],
                'source_version' => (int) $row['source_version'],
                'calculated_at' => is_string($row['calculated_at']) ? $row['calculated_at'] : null,
            ];
        }

        return $offers;
    }

    /** @return list<PricingRule> */
    private function rules(?int $commercialCatalogId): array
    {
        $sql = <<<'SQL'
SELECT rule.code, rule.scope, marketplace.code AS marketplace_code, rule.sku,
       rule.adjustment_type, rule.adjustment_value, rule.currency, rule.priority,
       rule.minimum_price_minor, rule.maximum_price_minor
FROM pricing_rules rule
LEFT JOIN marketplaces marketplace ON marketplace.id = rule.marketplace_id
WHERE rule.enabled
  AND rule.retired_at IS NULL
  AND (rule.valid_from IS NULL OR rule.valid_from <= :now)
  AND (rule.valid_until IS NULL OR rule.valid_until > :now)
SQL;
        if ($commercialCatalogId !== null) {
            $sql .= "\n  AND rule.commercial_catalog_id = :catalog_id";
        }
        $sql .= <<<'SQL'

ORDER BY rule.code
SQL;
        $statement = $this->connection()->prepare($sql);
        $parameters = ['now' => $this->clock->now()->format(DATE_ATOM)];
        if ($commercialCatalogId !== null) {
            $parameters['catalog_id'] = $commercialCatalogId;
        }
        $statement->execute($parameters);

        return array_values(array_map(static fn (array $row): PricingRule => new PricingRule(
            (string) $row['code'],
            PricingRuleScope::from((string) $row['scope']),
            is_string($row['marketplace_code']) ? $row['marketplace_code'] : null,
            is_string($row['sku']) ? $row['sku'] : null,
            PriceAdjustmentType::from((string) $row['adjustment_type']),
            (int) $row['adjustment_value'],
            (string) $row['currency'],
            (int) $row['priority'],
            $row['minimum_price_minor'] === null ? null : (int) $row['minimum_price_minor'],
            $row['maximum_price_minor'] === null ? null : (int) $row['maximum_price_minor'],
        ), $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /**
     * @param array<string, mixed> $product
     * @param array{id: int, code: string, name: string, business_status: string, technical_account_count: int} $marketplace
     * @param array<string, mixed> $preview
     * @return list<string>
     */
    private function blockers(array $product, array $marketplace, array $preview, bool $includedInCatalog): array
    {
        $blockers = [];
        $onboardingStatus = (string) ($product['onboarding_status'] ?? '');
        if ($onboardingStatus === 'rejected' || ($onboardingStatus === 'approved' && ($product['active'] ?? false) !== true)) {
            $blockers[] = 'prodotto bloccato';
        }
        $sellableQuantity = array_key_exists('sellable_quantity', $product)
            ? (int) $product['sellable_quantity']
            : max(
                0,
                (int) ($product['available_quantity'] ?? 0)
                + (int) ($product['backorder_quantity'] ?? 0)
                - (int) ($product['safety_stock'] ?? 0),
            );
        if ($sellableQuantity < 1) {
            $blockers[] = 'stock non disponibile';
        }
        if (!in_array($marketplace['business_status'], ['active', 'pilot'], true)) {
            $blockers[] = 'marketplace pianificato';
        }
        if ($marketplace['technical_account_count'] < 1) {
            $blockers[] = 'account tecnico non abilitato';
        }
        if (($preview['applied_rule_code'] ?? null) === null) {
            $blockers[] = 'nessuna regola di ricarico applicabile';
        }
        if (($preview['error'] ?? null) !== null) {
            $blockers[] = 'configurazione prezzo non valida';
        }
        if (!$includedInCatalog) {
            $blockers[] = 'prodotto non incluso nel catalogo';
        }

        return $blockers;
    }

    /** @return list<array<string, mixed>> */
    private function publicationRules(?int $commercialCatalogId): array
    {
        if ($commercialCatalogId === null) {
            return [];
        }
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT action, field, operator, match_value, marketplace_id, priority
FROM catalog_publication_rules
WHERE commercial_catalog_id = :catalog_id AND enabled AND retired_at IS NULL
ORDER BY priority, CASE action WHEN 'exclude' THEN 0 ELSE 1 END, id
SQL);
        $statement->execute(['catalog_id' => $commercialCatalogId]);

        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $product
     * @param list<array<string, mixed>> $rules
     */
    private function publicationAllows(array $product, int $marketplaceId, array $rules): bool
    {
        return CatalogPublicationRuleMatcher::allows($product, $marketplaceId, $rules);
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
