<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;

interface CatalogProductManagement
{
    public function review(
        int $id,
        int $expectedVersion,
        string $decision,
        UserIdentity $actor,
        string $correlationId,
    ): void;

    public function updateSafetyStock(
        int $id,
        int $expectedVersion,
        int $safetyStock,
        UserIdentity $actor,
        string $correlationId,
    ): void;
}
