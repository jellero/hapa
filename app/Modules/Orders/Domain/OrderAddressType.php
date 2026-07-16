<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

enum OrderAddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
}
