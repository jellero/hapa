<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Domain;

use Hapa\Modules\Catalog\Contract\Money;

final readonly class CalculatedPrice
{
    public function __construct(
        public Money $basePrice,
        public Money $sellingPrice,
        public ?string $appliedRuleCode,
    ) {
    }
}
