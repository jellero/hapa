<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use DateTimeImmutable;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testFrozenClockAlwaysReturnsTheInjectedInstant(): void
    {
        $instant = new DateTimeImmutable('2026-07-16T12:00:00+00:00');
        $clock = new FrozenClock($instant);

        self::assertSame($instant, $clock->now());
        self::assertSame($instant, $clock->now());
    }

    public function testSystemClockProducesUtcInstants(): void
    {
        self::assertSame('+00:00', (new SystemClock())->now()->format('P'));
    }
}
