<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

use InvalidArgumentException;

final readonly class PricingRule
{
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
    ) {
        $this->assertIdentifiers();
        $this->assertPricingValues();
        $this->assertScopeTargets();
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

    public function appliesTo(string $marketplaceCode, string $sku): bool
    {
        return match ($this->scope) {
            PricingRuleScope::Global => true,
            PricingRuleScope::Marketplace => $this->marketplaceCode === $marketplaceCode,
            PricingRuleScope::Sku => $this->sku === $sku,
            PricingRuleScope::MarketplaceSku => $this->marketplaceCode === $marketplaceCode && $this->sku === $sku,
        };
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
}
