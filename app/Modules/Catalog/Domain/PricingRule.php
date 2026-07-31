<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

use InvalidArgumentException;

final readonly class PricingRule
{
    private const MATCH_FIELDS = [
        'sku', 'ean', 'supplier_id', 'branch_suffix', 'artist', 'title', 'format', 'label',
        'category', 'family', 'group', 'delivery_time_days', 'available_quantity',
    ];
    private const MATCH_OPERATORS = ['equals', 'contains', 'starts_with', 'ends_with', 'minimum', 'maximum'];

    public function __construct(
        public string $code,
        public PricingRuleScope $scope,
        public ?string $marketplaceCode,
        public ?string $sku,
        public PriceAdjustmentType $adjustmentType,
        public int $adjustmentValue,
        public string $currency,
        public int $priority = 100,
        public ?int $minimumPriceMinor = null,
        public ?int $maximumPriceMinor = null,
        public ?string $matchField = null,
        public ?string $matchOperator = null,
        public ?string $matchValue = null,
    ) {
        $this->assertIdentifiers();
        $this->assertPricingValues();
        $this->assertScopeTargets();
        $this->assertProductMatch();
    }

    private function assertIdentifiers(): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,95}$/', $this->code) !== 1) {
            throw new InvalidArgumentException('Il codice della regola prezzo non è valido.');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('La valuta della regola prezzo non è valida.');
        }

        if ($this->marketplaceCode !== null && preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $this->marketplaceCode) !== 1) {
            throw new InvalidArgumentException('Il codice marketplace della regola prezzo non è valido.');
        }

        if ($this->sku !== null && (trim($this->sku) !== $this->sku || $this->sku === '' || strlen($this->sku) > 160)) {
            throw new InvalidArgumentException('Lo SKU della regola prezzo non è valido.');
        }
    }

    private function assertPricingValues(): void
    {
        if ($this->priority < 0 || $this->priority > 100_000) {
            throw new InvalidArgumentException('La priorità della regola deve essere compresa tra 0 e 100000.');
        }

        if ($this->adjustmentValue < 0) {
            throw new InvalidArgumentException('Il ricarico non può essere negativo.');
        }

        if ($this->adjustmentType === PriceAdjustmentType::Percentage && $this->adjustmentValue > 100_000) {
            throw new InvalidArgumentException('Il ricarico percentuale supera il limite del 1000%.');
        }

        if ($this->adjustmentType === PriceAdjustmentType::FixedPrice && $this->adjustmentValue === 0) {
            throw new InvalidArgumentException('Il prezzo fisso deve essere positivo.');
        }

        if ($this->minimumPriceMinor !== null && $this->minimumPriceMinor < 0) {
            throw new InvalidArgumentException('Il prezzo minimo non può essere negativo.');
        }

        if ($this->maximumPriceMinor !== null && $this->maximumPriceMinor < 0) {
            throw new InvalidArgumentException('Il prezzo massimo non può essere negativo.');
        }

        if ($this->minimumPriceMinor !== null && $this->maximumPriceMinor !== null && $this->minimumPriceMinor > $this->maximumPriceMinor) {
            throw new InvalidArgumentException('Il prezzo minimo non può superare il prezzo massimo.');
        }
    }

    /** @param array<string, mixed> $product */
    public function appliesTo(string $marketplaceCode, string $sku, array $product = []): bool
    {
        $scopeMatches = match ($this->scope) {
            PricingRuleScope::Global => true,
            PricingRuleScope::Marketplace => $this->marketplaceCode === $marketplaceCode,
            PricingRuleScope::Sku => $this->sku === $sku,
            PricingRuleScope::MarketplaceSku => $this->marketplaceCode === $marketplaceCode && $this->sku === $sku,
        };

        return $scopeMatches && $this->matchesProduct(['sku' => $sku, ...$product]);
    }

    public function specificity(): int
    {
        return $this->scope->specificity() + ($this->matchField === null ? 0 : 2);
    }

    private function assertScopeTargets(): void
    {
        $hasMarketplace = $this->marketplaceCode !== null;
        $hasSku = $this->sku !== null;
        $valid = match ($this->scope) {
            PricingRuleScope::Global => !$hasMarketplace && !$hasSku,
            PricingRuleScope::Marketplace => $hasMarketplace && !$hasSku,
            PricingRuleScope::Sku => !$hasMarketplace && $hasSku,
            PricingRuleScope::MarketplaceSku => $hasMarketplace && $hasSku,
        };

        if (!$valid) {
            throw new InvalidArgumentException('I destinatari non sono coerenti con l’ambito della regola prezzo.');
        }
    }

    private function assertProductMatch(): void
    {
        $values = [$this->matchField, $this->matchOperator, $this->matchValue];
        if ($values === [null, null, null]) {
            return;
        }
        if ($this->matchField === null || $this->matchOperator === null || $this->matchValue === null
            || !in_array($this->matchField, self::MATCH_FIELDS, true)
            || !in_array($this->matchOperator, self::MATCH_OPERATORS, true)
            || trim($this->matchValue) === '' || mb_strlen($this->matchValue) > 500) {
            throw new InvalidArgumentException('La condizione prodotto della regola prezzo non è valida.');
        }
        if (in_array($this->matchField, ['delivery_time_days', 'available_quantity'], true)
            && (!ctype_digit($this->matchValue)
                || !in_array($this->matchOperator, ['equals', 'minimum', 'maximum'], true))) {
            throw new InvalidArgumentException('La condizione numerica della regola prezzo non è valida.');
        }
    }

    /** @param array<string, mixed> $product */
    private function matchesProduct(array $product): bool
    {
        if ($this->matchField === null || $this->matchOperator === null || $this->matchValue === null) {
            return true;
        }
        $actualValues = $this->matchField === 'supplier_id'
            ? [$product['supplier_code'] ?? null, $product['supplier_id'] ?? null, $product['supplier_name'] ?? null]
            : [$product[$this->matchField] ?? null];

        foreach ($actualValues as $actual) {
            if ($this->valueMatches($actual, $this->matchOperator, $this->matchValue)) {
                return true;
            }
        }

        return false;
    }

    private function valueMatches(mixed $actual, string $operator, string $expected): bool
    {
        if (in_array($operator, ['minimum', 'maximum'], true)) {
            if (!is_int($actual) && !is_numeric($actual)) {
                return false;
            }

            return $operator === 'minimum' ? (int) $actual >= (int) $expected : (int) $actual <= (int) $expected;
        }
        $actual = mb_strtolower(trim((string) $actual));
        $expected = mb_strtolower(trim($expected));

        return match ($operator) {
            'equals' => $actual === $expected,
            'starts_with' => str_starts_with($actual, $expected),
            'ends_with' => str_ends_with($actual, $expected),
            default => str_contains($actual, $expected),
        };
    }
}
