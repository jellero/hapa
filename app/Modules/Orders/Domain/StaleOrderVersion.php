<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

final class StaleOrderVersion extends OrderDomainException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(sprintf(
            'Versione ordine obsoleta: attesa %d, corrente %d.',
            $expected,
            $actual,
        ));
    }
}
