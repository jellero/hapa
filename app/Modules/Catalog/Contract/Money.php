<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Contract;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minorAmount,
        public string $currency,
    ) {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException('L’importo monetario non può essere negativo.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('La valuta deve essere un codice ISO 4217 di tre lettere maiuscole.');
        }
    }
}
