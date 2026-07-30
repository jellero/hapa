<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;

interface CommercialCatalogManagement
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return list<array<string, mixed>> */
    public function preview(int $id, int $limit = 200): array;

    public function setEnabled(
        int $id,
        bool $enabled,
        UserIdentity $actor,
        string $correlationId,
    ): void;

    /** @param array<string, mixed> $input */
    public function create(array $input, UserIdentity $actor): int;

    public function delete(
        int $id,
        string $confirmation,
        UserIdentity $actor,
        string $correlationId,
    ): void;
}
