<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use InvalidArgumentException;

final readonly class ExternalOrderReference
{
    public function __construct(
        public MarketplaceChannel $channel,
        public string $externalOrderId,
    ) {
        if (trim($externalOrderId) === '') {
            throw new InvalidArgumentException('L’identificativo ordine esterno è obbligatorio.');
        }
    }
}
