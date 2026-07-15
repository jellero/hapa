<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Logging\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

final class SensitiveDataRedactorTest extends TestCase
{
    public function testItRedactsSensitiveKeysRecursively(): void
    {
        $result = (new SensitiveDataRedactor())->redact([
            'authorization' => 'Bearer secret',
            'customer' => [
                'name' => 'Mario Rossi',
                'fiscal_code' => 'RSSMRA...',
            ],
            'status' => 'ok',
        ]);

        self::assertSame('[REDACTED]', $result['authorization']);
        self::assertSame('[REDACTED]', $result['customer']['fiscal_code']);
        self::assertSame('Mario Rossi', $result['customer']['name']);
        self::assertSame('ok', $result['status']);
    }
}
