<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

interface MarketplaceAdapter
{
    /** @return list<array<string, mixed>> */
    public function importOpenOrders(): array;

    public function acceptOrder(string $externalOrderId): void;

    /** @return array<string, mixed>|null */
    public function fetchShippingAddress(string $externalOrderId): ?array;

    public function sendTracking(
        string $externalOrderId,
        string $carrier,
        string $trackingNumber,
        bool $partial,
    ): void;
}
