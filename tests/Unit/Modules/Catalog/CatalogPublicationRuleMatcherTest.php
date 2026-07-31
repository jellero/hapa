<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Catalog;

use Hapa\Modules\Catalog\Application\CatalogPublicationRuleMatcher;
use PHPUnit\Framework\TestCase;

final class CatalogPublicationRuleMatcherTest extends TestCase
{
    public function testTheHighestPriorityMatchWinsAndExclusionWinsATie(): void
    {
        $product = ['sku' => '98599483A25'];
        $include = $this->rule('include', 'contains', '98599483', 10);
        $lowerPriorityExclusion = $this->rule('exclude', 'contains', 'A25', 100);

        self::assertTrue(CatalogPublicationRuleMatcher::allows(
            $product,
            7,
            [$include, $lowerPriorityExclusion],
        ));

        $tiedExclusion = $this->rule('exclude', 'contains', 'A25', 10);
        self::assertFalse(CatalogPublicationRuleMatcher::allows(
            $product,
            7,
            [$tiedExclusion, $include],
        ));
    }

    public function testAnExclusionAloneNeverIncludesAProduct(): void
    {
        self::assertFalse(CatalogPublicationRuleMatcher::allows(
            ['sku' => '98599483A48'],
            7,
            [$this->rule('exclude', 'contains', 'A25', 100)],
        ));
    }

    public function testItMatchesEanAndTheOfficialSupplierCode(): void
    {
        $product = [
            'ean' => '5099703247626',
            'supplier_id' => '5',
            'supplier_code' => 'AEC',
            'supplier_name' => 'AEC ALLIANCE ENTERTAINMENT',
        ];

        self::assertTrue(CatalogPublicationRuleMatcher::matches($product, [
            ...$this->rule('include', 'equals', '5099703247626', 100),
            'field' => 'ean',
        ]));
        self::assertTrue(CatalogPublicationRuleMatcher::matches($product, [
            ...$this->rule('include', 'equals', 'aec', 100),
            'field' => 'supplier_id',
        ]));
    }

    /** @return array<string, int|string|null> */
    private function rule(string $action, string $operator, string $value, int $priority): array
    {
        return [
            'action' => $action,
            'field' => 'sku',
            'operator' => $operator,
            'match_value' => $value,
            'marketplace_id' => null,
            'priority' => $priority,
        ];
    }
}
