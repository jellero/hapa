<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

enum MarketplaceConnector: string
{
    case SellRapido = 'sellrapido';
    case Amazon = 'amazon';
    case Emag = 'emag';
    case Temu = 'temu';

    public function isAggregator(): bool
    {
        return $this === self::SellRapido;
    }
}
