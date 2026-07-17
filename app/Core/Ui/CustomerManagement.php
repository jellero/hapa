<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;

interface CustomerManagement
{
    /** @param array<string, mixed> $input */
    public function create(array $input, UserIdentity $actor, string $correlationId): string;

    /** @param array<string, mixed> $input */
    public function update(
        string $customerCode,
        int $expectedVersion,
        array $input,
        UserIdentity $actor,
        string $correlationId,
    ): void;

    public function archive(
        string $customerCode,
        int $expectedVersion,
        UserIdentity $actor,
        string $correlationId,
    ): void;
}
