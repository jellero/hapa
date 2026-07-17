<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

interface PricingPreview
{
    /**
     * @param list<array<string, mixed>> $products
     * @return array<int, list<array<string, mixed>>>
     */
    public function forProducts(array $products): array;
}
