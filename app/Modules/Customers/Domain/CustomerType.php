<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

enum CustomerType: string
{
    case Person = 'person';
    case Business = 'business';
}
