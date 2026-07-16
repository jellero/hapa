<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

use Hapa\Modules\Catalog\Contract\Money;
use InvalidArgumentException;
use OverflowException;

final class PriceCalculator
{
    /**
     * @param list<PricingRule> $rules
     */
    public function calculate(Money $basePrice, string $marketplaceCode, string $sku, array $rules): CalculatedPrice
    {
        $applicable = array_values(array_filter(
            $rules,
            static fn (PricingRule $rule): bool => $rule->appliesTo($marketplaceCode, $sku),
        ));

        usort($applicable, static function (PricingRule $left, PricingRule $right): int {
            $specificity = $right->scope->specificity() <=> $left->scope->specificity();
            if ($specificity !== 0) {
                return $specificity;
            }

            $priority = $right->priority <=> $left->priority;

            return $priority !== 0 ? $priority : $left->code <=> $right->code;
        });

        $rule = $applicable[0] ?? null;
        if ($rule === null) {
            return new CalculatedPrice($basePrice, $basePrice, null);
        }

        if ($rule->currency !== $basePrice->currency) {
            throw new InvalidArgumentException('La valuta della regola non coincide con quella del prezzo Space.');
        }

        $amount = match ($rule->adjustmentType) {
            PriceAdjustmentType::Percentage => $this->percentage($basePrice->minorAmount, $rule->adjustmentValue),
            PriceAdjustmentType::FixedAmount => $this->add($basePrice->minorAmount, $rule->adjustmentValue),
            PriceAdjustmentType::FixedPrice => $rule->adjustmentValue,
        };

        if ($rule->minimumPriceMinor !== null) {
            $amount = max($amount, $rule->minimumPriceMinor);
        }

        if ($rule->maximumPriceMinor !== null) {
            $amount = min($amount, $rule->maximumPriceMinor);
        }

        return new CalculatedPrice(
            $basePrice,
            new Money($amount, $basePrice->currency),
            $rule->code,
        );
    }

    private function percentage(int $baseAmount, int $basisPoints): int
    {
        $multiplier = 10_000 + $basisPoints;
        if ($baseAmount > intdiv(PHP_INT_MAX - 5_000, $multiplier)) {
            throw new OverflowException('Il calcolo percentuale supera la capacità numerica supportata.');
        }

        return intdiv(($baseAmount * $multiplier) + 5_000, 10_000);
    }

    private function add(int $baseAmount, int $adjustment): int
    {
        if ($baseAmount > PHP_INT_MAX - $adjustment) {
            throw new OverflowException('Il calcolo del prezzo supera la capacità numerica supportata.');
        }

        return $baseAmount + $adjustment;
    }
}
