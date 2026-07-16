<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

enum MarketplaceChannel: string
{
    case Amazon = 'amazon';
    case Emag = 'emag';
    case Temu = 'temu';
    case Ibs = 'ibs';
}
