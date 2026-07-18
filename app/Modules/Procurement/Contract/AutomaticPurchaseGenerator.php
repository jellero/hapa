<?php

declare(strict_types=1);

namespace Hapa\Modules\Procurement\Contract;

interface AutomaticPurchaseGenerator
{
    public function generate(int $orderId, string $correlationId): void;
}
