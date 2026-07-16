<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

enum PriceAdjustmentType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FixedPrice = 'fixed_price';
}
