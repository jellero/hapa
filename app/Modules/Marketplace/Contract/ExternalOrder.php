<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use InvalidArgumentException;

final readonly class ExternalOrder
{
    /** @param non-empty-list<ExternalOrderLine> $lines */
    public function __construct(
        public ExternalOrderReference $reference,
        public string $currency,
        public array $lines,
    ) {
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new InvalidArgumentException('La valuta deve rispettare il formato ISO 4217 di tre lettere maiuscole.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Un ordine esterno deve contenere almeno una riga.');
        }

        foreach ($lines as $line) {
            if (!$line instanceof ExternalOrderLine) {
                throw new InvalidArgumentException('Le righe ordine devono essere istanze di ExternalOrderLine.');
            }
        }
    }
}
