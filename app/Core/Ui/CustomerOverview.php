<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

interface CustomerOverview
{
    /** @return list<array<string, mixed>> */
    public function search(string $query, string $status, int $limit = 100): array;

    /** @return array<string, mixed>|null */
    public function detail(string $customerCode): ?array;
}
