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
    public function forProducts(array $products): array
    {
        $marketplaces = $this->marketplaces();
        $rules = $this->rules();
        $previews = [];
        foreach ($products as $product) {
            $id = (int) ($product['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
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
                    'marketplace_code' => $marketplace['code'],
                    'marketplace_name' => $marketplace['name'],
                    'marketplace_status' => $marketplace['business_status'],
                    'technical_account_count' => $marketplace['technical_account_count'],
                    'base_price_minor' => $cost,
                    'selling_price_minor' => null,
                    'markup_minor' => null,
                    'currency' => $currency,
                    'applied_rule_code' => null,
                    'publishable' => false,
                    'blockers' => [],
                    'error' => null,
                ];
                try {
                    $calculated = $this->calculator->calculate($basePrice, $marketplace['code'], $sku, $rules);
                    $preview['selling_price_minor'] = $calculated->sellingPrice->minorAmount;
                    $preview['markup_minor'] = $calculated->sellingPrice->minorAmount - $cost;
                    $preview['applied_rule_code'] = $calculated->appliedRuleCode;
                } catch (Throwable $exception) {
                    $preview['error'] = $exception->getMessage();
                }
                $blockers = $this->blockers($product, $marketplace, $preview);
                $preview['blockers'] = $blockers;
                $preview['publishable'] = $blockers === [];
                $previews[$id][] = $preview;
            }
        }

        return $previews;
    }

    /** @return list<array{code: string, name: string, business_status: string, technical_account_count: int}> */
    private function marketplaces(): array
    {
        $statement = $this->connection()->query(<<<'SQL'
SELECT marketplace.code, marketplace.name, marketplace.business_status,
       COUNT(account.id) FILTER (
           WHERE account.technical_enabled
             AND account.status IN ('pilot', 'active')
       ) AS technical_account_count
FROM marketplaces marketplace
LEFT JOIN marketplace_accounts account ON account.marketplace_id = marketplace.id
WHERE marketplace.business_status <> 'retired'
GROUP BY marketplace.id
ORDER BY CASE marketplace.business_status WHEN 'active' THEN 0 WHEN 'pilot' THEN 1 ELSE 2 END,
         marketplace.name
SQL);
        if ($statement === false) {
            return [];
        }

        return array_values(array_map(static fn (array $row): array => [
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'business_status' => (string) $row['business_status'],
            'technical_account_count' => (int) $row['technical_account_count'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /** @return list<PricingRule> */
    private function rules(): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT rule.code, rule.scope, marketplace.code AS marketplace_code, rule.sku,
       rule.adjustment_type, rule.adjustment_value, rule.currency, rule.priority,
       rule.minimum_price_minor, rule.maximum_price_minor
FROM pricing_rules rule
LEFT JOIN marketplaces marketplace ON marketplace.id = rule.marketplace_id
WHERE rule.enabled
  AND rule.retired_at IS NULL
  AND (rule.valid_from IS NULL OR rule.valid_from <= :now)
  AND (rule.valid_until IS NULL OR rule.valid_until > :now)
ORDER BY rule.code
SQL);
        $statement->execute(['now' => $this->clock->now()->format(DATE_ATOM)]);

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
     * @param array{code: string, name: string, business_status: string, technical_account_count: int} $marketplace
     * @param array<string, mixed> $preview
     * @return list<string>
     */
    private function blockers(array $product, array $marketplace, array $preview): array
    {
        $blockers = [];
        if (($product['onboarding_status'] ?? null) !== 'approved') {
            $blockers[] = 'prodotto non approvato';
        }
        if (($product['active'] ?? false) !== true) {
            $blockers[] = 'prodotto inattivo';
        }
        if ((int) ($product['available_quantity'] ?? 0) < 1) {
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

        return $blockers;
    }

    private function connection(): PDO
    {
        return $this->connection ??= $this->connections->create();
    }
}
