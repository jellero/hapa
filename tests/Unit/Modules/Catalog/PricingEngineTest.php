<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Catalog;

use Hapa\Modules\Catalog\Contract\Money;
use Hapa\Modules\Catalog\Domain\PriceAdjustmentType;
use Hapa\Modules\Catalog\Domain\PriceCalculator;
use Hapa\Modules\Catalog\Domain\PricingRule;
use Hapa\Modules\Catalog\Domain\PricingRuleScope;
use Hapa\Modules\Catalog\Domain\ProductAvailability;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PricingEngineTest extends TestCase
{
    public function testItSubtractsSafetyStockWithoutPublishingNegativeAvailability(): void
    {
        self::assertSame(7, (new ProductAvailability(10, 3))->sellableQuantity());
        self::assertSame(0, (new ProductAvailability(2, 5))->sellableQuantity());
    }

    public function testTheMostSpecificRuleWinsAndPercentageUsesHalfUpRounding(): void
    {
        $calculator = new PriceCalculator();
        $base = new Money(1_999, 'EUR');
        $global = $this->rule(
            'global-15',
            PricingRuleScope::Global,
            PriceAdjustmentType::Percentage,
            1_500,
        );
        $specific = $this->rule(
            'amazon-sku-1',
            PricingRuleScope::MarketplaceSku,
            PriceAdjustmentType::FixedAmount,
            500,
            'amazon',
            'SKU-1',
        );

        $result = $calculator->calculate($base, 'amazon', 'SKU-1', [$global, $specific]);

        self::assertSame(2_499, $result->sellingPrice->minorAmount);
        self::assertSame('amazon-sku-1', $result->appliedRuleCode);

        $percentageOnly = $calculator->calculate($base, 'emag', 'SKU-1', [$global, $specific]);
        self::assertSame(2_299, $percentageOnly->sellingPrice->minorAmount);
    }

    public function testHigherPriorityBreaksATieAndPriceBoundariesAreApplied(): void
    {
        $calculator = new PriceCalculator();
        $lowerPriority = $this->rule(
            'amazon-standard',
            PricingRuleScope::Marketplace,
            PriceAdjustmentType::Percentage,
            1_000,
            'amazon',
            priority: 100,
        );
        $higherPriority = $this->rule(
            'amazon-capped',
            PricingRuleScope::Marketplace,
            PriceAdjustmentType::FixedAmount,
            1_000,
            'amazon',
            priority: 200,
            maximumPriceMinor: 2_500,
        );

        $result = $calculator->calculate(
            new Money(2_000, 'EUR'),
            'amazon',
            'SKU-1',
            [$lowerPriority, $higherPriority],
        );

        self::assertSame(2_500, $result->sellingPrice->minorAmount);
        self::assertSame('amazon-capped', $result->appliedRuleCode);
    }

    public function testItKeepsTheSpacePriceWhenNoRuleApplies(): void
    {
        $base = new Money(1_250, 'EUR');
        $result = (new PriceCalculator())->calculate($base, 'temu', 'SKU-1', []);

        self::assertSame($base, $result->sellingPrice);
        self::assertNull($result->appliedRuleCode);
    }

    public function testItRejectsARuleWithIncoherentScopeTargets(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rule(
            'bad-scope',
            PricingRuleScope::Global,
            PriceAdjustmentType::Percentage,
            500,
            'amazon',
        );
    }

    public function testItRejectsACurrencyMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PriceCalculator())->calculate(
            new Money(1_000, 'EUR'),
            'amazon',
            'SKU-1',
            [new PricingRule(
                'amazon-usd',
                PricingRuleScope::Marketplace,
                'amazon',
                null,
                PriceAdjustmentType::FixedAmount,
                100,
                'USD',
            )],
        );
    }

    private function rule(
        string $code,
        PricingRuleScope $scope,
        PriceAdjustmentType $type,
        int $value,
        ?string $marketplace = null,
        ?string $sku = null,
        int $priority = 100,
        ?int $minimumPriceMinor = null,
        ?int $maximumPriceMinor = null,
    ): PricingRule {
        return new PricingRule(
            $code,
            $scope,
            $marketplace,
            $sku,
            $type,
            $value,
            'EUR',
            $priority,
            $minimumPriceMinor,
            $maximumPriceMinor,
        );
    }
}
