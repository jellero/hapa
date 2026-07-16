<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use InvalidArgumentException;

final readonly class SpaceOrderRequest
{
    /** @param non-empty-list<array{sku: string, quantity: int}> $lines */
    public function __construct(
        public string $orderReference,
        public array $lines,
    ) {
        if (trim($orderReference) === '') {
            throw new InvalidArgumentException('Il riferimento ordine è obbligatorio.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Un ordine Space deve contenere almeno una riga.');
        }

        foreach ($lines as $line) {
            if (
                !isset($line['sku'], $line['quantity'])
                || !is_string($line['sku'])
                || !is_int($line['quantity'])
                || trim($line['sku']) === ''
                || $line['quantity'] < 1
            ) {
                throw new InvalidArgumentException('Ogni riga Space richiede SKU e quantità positiva.');
            }
        }
    }
}
