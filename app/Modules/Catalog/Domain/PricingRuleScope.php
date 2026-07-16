<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

enum PricingRuleScope: string
{
    case Global = 'global';
    case Marketplace = 'marketplace';
    case Sku = 'sku';
    case MarketplaceSku = 'marketplace_sku';

    public function specificity(): int
    {
        return match ($this) {
            self::Global => 0,
            self::Marketplace => 1,
            self::Sku => 2,
            self::MarketplaceSku => 3,
        };
    }
}
