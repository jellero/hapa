<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

interface MarketplaceOfferAdapter
{
    public function publishOffer(
        MarketplaceOfferUpdate $offer,
        string $idempotencyKey,
    ): MarketplaceOfferPublication;
}
