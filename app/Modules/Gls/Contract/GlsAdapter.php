<?php

declare(strict_types=1);

namespace Hapa\Modules\Gls\Contract;

interface GlsAdapter
{
    /**
     * @param array<string, mixed> $shipment
     * @return array{shipment_id: string, tracking_number: string, label_reference: string}
     */
    public function createShipment(array $shipment, string $idempotencyKey): array;

    public function fetchLabel(string $labelReference): string;
}
