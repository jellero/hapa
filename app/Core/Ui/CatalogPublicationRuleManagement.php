<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;

interface CatalogPublicationRuleManagement
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @param array<string, mixed> $input */
    public function create(array $input, UserIdentity $actor): void;

    public function retire(int $id, UserIdentity $actor): void;
}
