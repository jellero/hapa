<?php

declare(strict_types=1);

namespace Hapa\Core\Clock;

use DateTimeImmutable;

final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $currentTime)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }
}
