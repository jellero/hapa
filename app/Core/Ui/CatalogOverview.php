<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

interface CatalogOverview
{
    /**
     * @param array<string, string> $filters
     * @return array{
     *   items: list<array<string, int|string|bool|null>>,
     *   metrics: array<string, int>,
     *   filter_options: array{feeds: list<string>, formats: list<string>, suppliers: list<array{id:string,name:?string}>}
     * }
     */
    public function search(string $query, int $limit = 100, array $filters = []): array;
}
