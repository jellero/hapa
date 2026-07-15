<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract\Dto;

use InvalidArgumentException;

final readonly class AvailabilityResult
{
    public function __construct(
        public string $sku,
        public int $requested,
        public int $available,
        public int $missing,
    ) {
        if (
            $sku === ''
            || $requested < 0
            || $available < 0
            || $missing < 0
            || $available + $missing !== $requested
        ) {
            throw new InvalidArgumentException('Disponibilità Space non valida.');
        }
    }
}
