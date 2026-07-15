<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

interface MarketplaceAdapter
{
    /** @return list<ExternalOrder> */
    public function importOpenOrders(): array;

    public function acceptOrder(string $externalOrderId): void;

    public function fetchShippingAddress(string $externalOrderId): ?ShippingAddress;

    public function sendTracking(TrackingNotification $notification): void;
}
