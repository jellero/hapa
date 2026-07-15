<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

final readonly class ExternalOrder
{
    /** @param list<array{external_line_id: string|null, sku: string, ean: string|null, quantity: int}> $lines */
    public function __construct(
        public string $externalOrderId,
        public string $currency,
        public array $lines,
    ) {
    }
}
