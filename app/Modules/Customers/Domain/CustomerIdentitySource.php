<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

enum CustomerIdentitySource: string
{
    case Amazon = 'amazon';
    case Emag = 'emag';
    case Temu = 'temu';
    case Ibs = 'ibs';
    case B2cEcommerce = 'b2c_ecommerce';
}
