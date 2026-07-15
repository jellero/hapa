<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract\Dto;

use InvalidArgumentException;

final readonly class ExternalOrder
{
    /** @param list<ExternalOrderLine> $lines */
    public function __construct(
        public string $externalOrderId,
        public string $currency,
        public array $lines,
    ) {
        if ($externalOrderId === '' || !preg_match('/^[A-Z]{3}$/D', $currency) || $lines === []) {
            throw new InvalidArgumentException('Ordine marketplace non valido.');
        }
    }
}
