<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

interface CatalogOverview
{
    /**
     * @return array{
     *   items: list<array<string, int|string|bool|null>>,
     *   metrics: array{total: int, pending_review: int, active: int, stale: int}
     * }
     */
    public function search(string $query, int $limit = 100): array;
}
