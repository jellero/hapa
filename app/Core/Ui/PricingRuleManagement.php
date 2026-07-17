<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;

interface PricingRuleManagement
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @return list<array{id: int, code: string, name: string}> */
    public function marketplaces(): array;

    /** @param array<string, mixed> $input */
    public function create(array $input, UserIdentity $actor, string $correlationId): int;

    /** @param array<string, mixed> $input */
    public function update(
        int $id,
        int $expectedVersion,
        array $input,
        UserIdentity $actor,
        string $correlationId,
    ): void;

    public function retire(int $id, int $expectedVersion, UserIdentity $actor, string $correlationId): void;
}
