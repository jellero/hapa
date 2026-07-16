<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Contract;

enum CarrierCode: string
{
    case Gls = 'GLS';
    case Brt = 'BRT';
}
