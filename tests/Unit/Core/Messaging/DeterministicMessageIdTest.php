<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Messaging;

use Hapa\Core\Messaging\DeterministicMessageId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeterministicMessageIdTest extends TestCase
{
    public function testItCreatesAStableSha256BasedUuidV8(): void
    {
        $first = DeterministicMessageId::fromIdempotencyKey('order:ORD-001:v2:order.status_changed');
        $second = DeterministicMessageId::fromIdempotencyKey(' order:ORD-001:v2:order.status_changed ');

        self::assertSame('e834feda-c306-870a-af22-9e1d757b212d', $first);
        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-8[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
    }

    public function testItRejectsAnEmptyIdempotencyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DeterministicMessageId::fromIdempotencyKey('  ');
    }
}
