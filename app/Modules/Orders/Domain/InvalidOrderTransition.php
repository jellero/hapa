<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

final class InvalidOrderTransition extends OrderDomainException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(sprintf(
            'Transizione ordine non consentita da %s a %s.',
            $from->value,
            $to->value,
        ));
    }
}
