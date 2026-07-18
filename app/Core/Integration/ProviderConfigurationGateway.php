<?php

declare(strict_types=1);

namespace Hapa\Core\Integration;

interface ProviderConfigurationGateway
{
    /**
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    public function apply(array $account, string $actorId, string $correlationId): array;

    /** @return array<string, mixed> */
    public function configurationStatus(string $account): array;

    /** @return array<string, mixed> */
    public function testConnection(string $account): array;

    /** @return array<string, mixed> */
    public function importOrders(string $account): array;

    /** @return array<string, mixed> */
    public function synchronizeCatalog(string $account): array;
}
