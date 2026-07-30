<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

interface SpaceSupplierOverview
{
    /**
     * @return array{
     *   items:list<array<string,int|string|bool|null>>,
     *   metrics:array{total:int,active:int,inactive:int,countries:int}
     * }
     */
    public function search(string $query, string $status = '', int $limit = 200): array;
}
