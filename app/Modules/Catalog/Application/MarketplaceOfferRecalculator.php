<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Modules\Catalog\Contract\CatalogOfferRecalculator;
use Hapa\Modules\Catalog\Contract\Money;
use Hapa\Modules\Catalog\Domain\PriceAdjustmentType;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Catalog\Domain\PricingRule;
use Hapa\Modules\Catalog\Domain\PricingRuleScope;
use PDO;
use RuntimeException;
use Throwable;

final readonly class MarketplaceOfferRecalculator implements CatalogOfferRecalculator
{
    public function __construct(
        private PriceCalculator $calculator,
        private Clock $clock,
        private ?ProviderCommandFactory $commands = null,
    ) {
    }

    public function recalculateProduct(PDO $pdo, int $catalogItemId): int
    {
        if ($catalogItemId < 1) {
            throw new RuntimeException('Prodotto HAPA non valido per il ricalcolo offerte.');
        }

        return $this->recalculate($pdo, [$catalogItemId]);
    }

    public function recalculateAll(PDO $pdo): int
    {
        $statement = $pdo->query('SELECT id FROM catalog_items ORDER BY id');
        if ($statement === false) {
            throw new RuntimeException('Impossibile leggere i prodotti da ricalcolare.');
        }

        return $this->recalculate($pdo, array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $statement->fetchAll(PDO::FETCH_COLUMN),
        )));
    }

    /** @param list<int> $catalogItemIds */
    private function recalculate(PDO $pdo, array $catalogItemIds): int
    {
        if ($catalogItemIds === []) {
            return 0;
        }

        $marketplaces = $this->marketplaces($pdo);
        [$rules, $ruleIds] = $this->rules($pdo);
        $updatedOffers = 0;
        foreach ($catalogItemIds as $catalogItemId) {
            $product = $this->product($pdo, $catalogItemId);
            $availableQuantity = (int) ($product['available_quantity'] ?? 0);
            $sellableQuantity = max(0, $availableQuantity - (int) $product['safety_stock']);
            $now = $this->clock->now()->format(DATE_ATOM);

            $updateProduct = $pdo->prepare(<<<'SQL'
UPDATE catalog_items
SET sellable_quantity = :sellable_quantity,
    offers_calculated_at = :calculated_at
WHERE id = :id
SQL);
            $updateProduct->execute([
                'sellable_quantity' => $sellableQuantity,
                'calculated_at' => $now,
                'id' => $catalogItemId,
            ]);

            foreach ($marketplaces as $marketplace) {
                $price = null;
                $appliedRuleId = null;
                $calculationValid = false;
                if ($product['purchase_cost_minor'] !== null && $product['offer_active']) {
                    try {
                        $calculated = $this->calculator->calculate(
                            new Money((int) $product['purchase_cost_minor'], (string) $product['currency']),
                            (string) $marketplace['code'],
                            (string) $product['sku'],
                            $rules,
                        );
                        if ($calculated->appliedRuleCode !== null) {
                            $price = $calculated->sellingPrice->minorAmount;
                            $appliedRuleId = $ruleIds[$calculated->appliedRuleCode] ?? null;
                            $calculationValid = $appliedRuleId !== null;
                        }
                    } catch (Throwable) {
                        $calculationValid = false;
                    }
                }

                $eligible = $calculationValid
                    && $product['active']
                    && $product['onboarding_status'] === 'approved'
                    && in_array($marketplace['business_status'], ['pilot', 'active'], true);
                $changedOffer = $this->saveOffer(
                    $pdo,
                    $product,
                    $marketplace,
                    $price,
                    $sellableQuantity,
                    $appliedRuleId,
                    $eligible,
                    $now,
                );
                if ($changedOffer !== null) {
                    ++$updatedOffers;
                    $this->requestPublication($pdo, $product, $marketplace, $changedOffer, $now);
                }
            }
        }

        return $updatedOffers;
    }

    /** @return array<string, mixed> */
    private function product(PDO $pdo, int $catalogItemId): array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT item.id, item.sku, item.onboarding_status, item.active, item.safety_stock,
       offer.purchase_cost_minor, COALESCE(offer.currency, item.currency) AS currency,
       offer.available_quantity, COALESCE(offer.active, FALSE) AS offer_active
FROM catalog_items AS item
LEFT JOIN supplier_catalog_items AS offer
  ON offer.catalog_item_id = item.id
 AND offer.supplier_id = (SELECT id FROM suppliers WHERE code = 'space' LIMIT 1)
WHERE item.id = :id
FOR UPDATE OF item
SQL);
        $statement->execute(['id' => $catalogItemId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Prodotto HAPA non trovato per il ricalcolo offerte.');
        }

        return [
            'id' => (int) $row['id'],
            'sku' => (string) $row['sku'],
            'onboarding_status' => (string) $row['onboarding_status'],
            'active' => filter_var($row['active'], FILTER_VALIDATE_BOOL),
            'safety_stock' => (int) $row['safety_stock'],
            'purchase_cost_minor' => $row['purchase_cost_minor'] === null ? null : (int) $row['purchase_cost_minor'],
            'currency' => (string) $row['currency'],
            'available_quantity' => $row['available_quantity'] === null ? 0 : (int) $row['available_quantity'],
            'offer_active' => filter_var($row['offer_active'], FILTER_VALIDATE_BOOL),
        ];
    }

    /** @return list<array{id: int, code: string, business_status: string}> */
    private function marketplaces(PDO $pdo): array
    {
        $statement = $pdo->query(<<<'SQL'
SELECT id, code, business_status
FROM marketplaces
WHERE business_status <> 'retired'
ORDER BY id
SQL);
        if ($statement === false) {
            throw new RuntimeException('Impossibile leggere i marketplace per il ricalcolo offerte.');
        }

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'business_status' => (string) $row['business_status'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    /** @return array{0: list<PricingRule>, 1: array<string, int>} */
    private function rules(PDO $pdo): array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT rule.id, rule.code, rule.scope, marketplace.code AS marketplace_code, rule.sku,
       rule.adjustment_type, rule.adjustment_value, rule.currency, rule.priority,
       rule.minimum_price_minor, rule.maximum_price_minor
FROM pricing_rules AS rule
LEFT JOIN marketplaces AS marketplace ON marketplace.id = rule.marketplace_id
WHERE rule.enabled
  AND rule.retired_at IS NULL
  AND (rule.valid_from IS NULL OR rule.valid_from <= :now)
  AND (rule.valid_until IS NULL OR rule.valid_until > :now)
ORDER BY rule.code
SQL);
        $statement->execute(['now' => $this->clock->now()->format(DATE_ATOM)]);
        $rules = [];
        $ids = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = (string) $row['code'];
            $rules[] = new PricingRule(
                $code,
                PricingRuleScope::from((string) $row['scope']),
                is_string($row['marketplace_code']) ? $row['marketplace_code'] : null,
                is_string($row['sku']) ? $row['sku'] : null,
                PriceAdjustmentType::from((string) $row['adjustment_type']),
                (int) $row['adjustment_value'],
                (string) $row['currency'],
                (int) $row['priority'],
                $row['minimum_price_minor'] === null ? null : (int) $row['minimum_price_minor'],
                $row['maximum_price_minor'] === null ? null : (int) $row['maximum_price_minor'],
            );
            $ids[$code] = (int) $row['id'];
        }

        return [$rules, $ids];
    }

    /**
     * @param array<string, mixed> $product
     * @param array{id: int, code: string, business_status: string} $marketplace
     * @return array<string, mixed>|null
     */
    private function saveOffer(
        PDO $pdo,
        array $product,
        array $marketplace,
        ?int $price,
        int $quantity,
        ?int $appliedRuleId,
        bool $eligible,
        string $now,
    ): ?array {
        $existing = $pdo->prepare(<<<'SQL'
SELECT id, desired_price_minor, currency, desired_quantity, applied_pricing_rule_id,
       source_version, status
FROM marketplace_offers
WHERE catalog_item_id = :catalog_item_id
  AND marketplace_id = :marketplace_id
  AND marketplace_account_id IS NULL
FOR UPDATE
SQL);
        $existing->execute([
            'catalog_item_id' => $product['id'],
            'marketplace_id' => $marketplace['id'],
        ]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        $currency = (string) $product['currency'];
        if (!is_array($row)) {
            $insert = $pdo->prepare(<<<'SQL'
INSERT INTO marketplace_offers (
    catalog_item_id, marketplace_id, marketplace_account_id,
    desired_price_minor, currency, desired_quantity, applied_pricing_rule_id,
    source_version, status, calculated_at, created_at, updated_at
) VALUES (
    :catalog_item_id, :marketplace_id, NULL,
    :desired_price_minor, :currency, :desired_quantity, :applied_pricing_rule_id,
    1, :status, :calculated_at, :created_at, :updated_at
)
RETURNING id, source_version, status, desired_price_minor, currency, desired_quantity
SQL);
            $insert->execute([
                'catalog_item_id' => $product['id'],
                'marketplace_id' => $marketplace['id'],
                'desired_price_minor' => $price,
                'currency' => $currency,
                'desired_quantity' => $quantity,
                'applied_pricing_rule_id' => $appliedRuleId,
                'status' => $eligible ? 'pending' : 'disabled',
                'calculated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $inserted = $insert->fetch(PDO::FETCH_ASSOC);
            if (!is_array($inserted)) {
                throw new RuntimeException('Offerta HAPA creata ma non recuperabile.');
            }

            return $inserted;
        }

        $targetChanged = self::nullableInt($row['desired_price_minor']) !== $price
            || (string) $row['currency'] !== $currency
            || (int) $row['desired_quantity'] !== $quantity
            || self::nullableInt($row['applied_pricing_rule_id']) !== $appliedRuleId;
        $currentStatus = (string) $row['status'];
        $status = !$eligible
            ? 'disabled'
            : (($targetChanged || $currentStatus === 'disabled') ? 'pending' : $currentStatus);
        $stateChanged = $targetChanged || $status !== $currentStatus;
        $update = $pdo->prepare(<<<'SQL'
UPDATE marketplace_offers
SET desired_price_minor = :desired_price_minor,
    currency = :currency,
    desired_quantity = :desired_quantity,
    applied_pricing_rule_id = :applied_pricing_rule_id,
    source_version = source_version + :version_increment,
    status = :status,
    last_error = CASE WHEN :clear_error = 1 THEN NULL ELSE last_error END,
    calculated_at = :calculated_at,
    updated_at = CASE WHEN :state_changed = 1 THEN :updated_at ELSE updated_at END
WHERE id = :id
RETURNING id, source_version, status, desired_price_minor, currency, desired_quantity
SQL);
        $update->execute([
            'desired_price_minor' => $price,
            'currency' => $currency,
            'desired_quantity' => $quantity,
            'applied_pricing_rule_id' => $appliedRuleId,
            'version_increment' => $stateChanged ? 1 : 0,
            'status' => $status,
            'clear_error' => $stateChanged ? 1 : 0,
            'calculated_at' => $now,
            'state_changed' => $stateChanged ? 1 : 0,
            'updated_at' => $now,
            'id' => (int) $row['id'],
        ]);

        if (!$stateChanged) {
            return null;
        }
        $updated = $update->fetch(PDO::FETCH_ASSOC);
        if (!is_array($updated)) {
            throw new RuntimeException('Offerta HAPA aggiornata ma non recuperabile.');
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $product
     * @param array{id: int, code: string, business_status: string} $marketplace
     * @param array<string, mixed> $offer
     */
    private function requestPublication(
        PDO $pdo,
        array $product,
        array $marketplace,
        array $offer,
        string $now,
    ): void {
        if ($this->commands === null) {
            return;
        }
        $account = $this->sellRapidoAccount($pdo, (string) $marketplace['code'], (string) $product['sku']);
        if ($account === null || $offer['desired_price_minor'] === null || (string) $offer['status'] === 'disabled') {
            return;
        }

        $offerId = (string) $offer['id'];
        $version = (int) $offer['source_version'];
        $catalogId = (int) $account['catalog_id'];
        $idempotencyKey = sprintf(
            'sellrapido:catalog:%d:sku:%s:offer:%d',
            $catalogId,
            (string) $product['sku'],
            $version,
        );
        $payload = [
            'integration_account_code' => (string) $account['code'],
            'configuration_version' => (int) $account['configuration_version'],
            'connector' => 'sellrapido',
            'downstream_marketplace_code' => (string) $marketplace['code'],
            'catalog_id' => $catalogId,
            'offer_id' => $offerId,
            'offer_version' => $version,
            'sku' => (string) $product['sku'],
            'price_minor' => (int) $offer['desired_price_minor'],
            'currency' => (string) $offer['currency'],
            'quantity' => (int) $offer['desired_quantity'],
            'idempotency_key' => $idempotencyKey,
        ];
        if (is_string($account['catalog_uuid']) && $account['catalog_uuid'] !== '') {
            $payload['catalog_uuid'] = $account['catalog_uuid'];
        }
        $appended = (new PostgresOutboxRepository($pdo))->append($this->commands->create(
            'marketplace.offer.publish.requested',
            'marketplace_offer',
            $offerId,
            $payload,
            'marketplace-offer-' . $offerId . '-v' . $version,
        ));
        if (!$appended) {
            return;
        }

        $statement = $pdo->prepare(<<<'SQL'
UPDATE marketplace_offers
SET status = 'syncing', last_idempotency_key = :idempotency_key, last_error = NULL, updated_at = :updated_at
WHERE id = :id AND source_version = :source_version
SQL);
        $statement->execute([
            'idempotency_key' => $idempotencyKey,
            'updated_at' => $now,
            'id' => (int) $offer['id'],
            'source_version' => $version,
        ]);
    }

    /** @return array{code:string,configuration_version:int,catalog_id:int,catalog_uuid:?string}|null */
    private function sellRapidoAccount(PDO $pdo, string $marketplaceCode, string $sku): ?array
    {
        $statement = $pdo->prepare(<<<'SQL'
SELECT account.code, account.configuration_version, account.desired_status,
       settings.setting_value::text AS settings
FROM integration_accounts AS account
JOIN integration_account_capabilities AS capability
  ON capability.integration_account_id = account.id
 AND capability.capability = 'products.write'
 AND capability.enabled
JOIN LATERAL (
    SELECT COALESCE(jsonb_object_agg(setting.setting_key, setting.setting_value), '{}'::jsonb) AS setting_value
    FROM integration_account_settings AS setting
    WHERE setting.integration_account_id = account.id
) AS settings ON TRUE
WHERE account.provider_code = 'sellrapido'
  AND account.desired_status IN ('pilot', 'active')
  AND account.secret_status = 'configured'
  AND account.connection_test_status = 'passed'
  AND account.automation_configuration_version = account.configuration_version
ORDER BY CASE account.desired_status WHEN 'active' THEN 0 ELSE 1 END, account.id
FOR SHARE OF account
SQL);
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings = json_decode((string) $row['settings'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($settings) || array_is_list($settings)) {
                continue;
            }
            $downstream = strtolower(trim((string) ($settings['downstream_channel'] ?? '')));
            if ($downstream !== strtolower($marketplaceCode)) {
                continue;
            }
            $catalogId = $settings['catalog_id'] ?? null;
            if (is_string($catalogId) && ctype_digit($catalogId)) {
                $catalogId = (int) $catalogId;
            }
            if (!is_int($catalogId) || $catalogId < 1) {
                continue;
            }
            if (($row['desired_status'] ?? null) === 'pilot') {
                $pilotSkus = $settings['pilot_skus'] ?? [];
                if (!is_array($pilotSkus) || !in_array($sku, $pilotSkus, true)) {
                    continue;
                }
            }

            $catalogUuid = $settings['catalog_uuid'] ?? null;

            return [
                'code' => (string) $row['code'],
                'configuration_version' => (int) $row['configuration_version'],
                'catalog_id' => $catalogId,
                'catalog_uuid' => is_string($catalogUuid) && trim($catalogUuid) !== '' ? trim($catalogUuid) : null,
            ];
        }

        return null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
