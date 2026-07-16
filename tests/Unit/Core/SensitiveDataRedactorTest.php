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
                'email' => 'mario@example.com',
                'shipping_address' => [
                    'street' => 'Via Roma 1',
                    'postal_code' => '00100',
                ],
                'fiscal_code' => 'RSSMRA...',
            ],
            'headers' => [
                'Set-Cookie' => 'session=secret',
            ],
            'ip_address' => '192.0.2.10',
            'status' => 'ok',
        ]);

        self::assertSame('[REDACTED]', $result['authorization']);
        self::assertSame('[REDACTED]', $result['customer']['fiscal_code']);
        self::assertSame('[REDACTED]', $result['customer']['email']);
        self::assertSame('[REDACTED]', $result['customer']['shipping_address']);
        self::assertSame('[REDACTED]', $result['headers']['Set-Cookie']);
        self::assertSame('[REDACTED]', $result['ip_address']);
        self::assertSame('Mario Rossi', $result['customer']['name']);
        self::assertSame('ok', $result['status']);
    }
}
