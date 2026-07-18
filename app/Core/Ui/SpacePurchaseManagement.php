<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

interface SpacePurchaseManagement
{
    public function generateForOrder(string $orderNumber, string $correlationId): void;

    /** @return array{examined:int,generated:int,manual_review:int,failed:int} */
    public function generateOutstanding(string $correlationId, int $limit = 500): array;
}
