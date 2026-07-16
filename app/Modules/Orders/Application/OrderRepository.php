<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Application;

use Hapa\Modules\Orders\Domain\Order;
use Hapa\Modules\Orders\Domain\OrderNumber;

interface OrderRepository
{
    public function find(OrderNumber $number): ?Order;

    public function save(Order $order, int $expectedVersion): void;
}
