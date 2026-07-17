<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Contract;

final readonly class StoredDocument
{
    public function __construct(
        public string $reference,
        public string $checksumSha256,
        public int $bytes,
        public string $format,
    ) {
    }
}
