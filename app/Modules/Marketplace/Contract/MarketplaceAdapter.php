<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

interface MarketplaceAdapter
{
    public function connector(): MarketplaceConnector;

    /** @return non-empty-list<MarketplaceChannel> */
    public function supportedChannels(): array;

    /** @return list<ExternalOrder> */
    public function importOpenOrders(): array;

    public function acceptOrder(ExternalOrderReference $order): void;

    public function fetchShippingAddress(ExternalOrderReference $order): ?ShippingAddress;

    public function sendTracking(TrackingNotification $notification): void;
}
