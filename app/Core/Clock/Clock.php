<?php

declare(strict_types=1);

namespace Hapa\Core\Clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
