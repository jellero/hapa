<?php

declare(strict_types=1);

namespace Hapa\Core\Integration;

interface ProviderSecretGateway
{
    /**
     * @param array<string, string> $secrets
     * @return array<string, mixed>
     */
    public function replace(string $account, string $provider, array $secrets, string $actorId, string $correlationId): array;

    /** @return array<string, mixed> */
    public function revoke(string $account, string $provider, string $actorId, string $correlationId): array;
}
