<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

enum OrderOrigin: string
{
    case Marketplace = 'marketplace';
    case B2cEcommerce = 'b2c_ecommerce';
}
