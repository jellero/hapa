<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Audit\AuditErrorDiagnostic;
use PHPUnit\Framework\TestCase;

final class AuditErrorDiagnosticTest extends TestCase
{
    public function testItExplainsTheInvalidSpaceFieldAndItsLimit(): void
    {
        $value = str_repeat('È', 160);

        $diagnostic = AuditErrorDiagnostic::from('messaging.inbox_failed', [
            'error' => 'Campo name non valido.',
            'payload' => ['name' => $value],
        ]);

        self::assertNotNull($diagnostic);
        self::assertSame('name', $diagnostic['field']);
        self::assertSame('160 caratteri · 320 byte', $diagnostic['observed']);
        self::assertSame('testo · massimo 255 byte · facoltativo', $diagnostic['expected']);
        self::assertStringContainsString('320 byte', (string) $diagnostic['cause']);
        self::assertStringContainsString('limite di 255', (string) $diagnostic['cause']);
        self::assertSame($value, $diagnostic['value']);
    }

    public function testItLeavesUnstructuredErrorsReadable(): void
    {
        $diagnostic = AuditErrorDiagnostic::from('security.login_failed', [
            'error' => 'Credenziali non valide.',
        ]);

        self::assertNotNull($diagnostic);
        self::assertSame('Credenziali non valide.', $diagnostic['message']);
        self::assertNull($diagnostic['field']);
        self::assertNull($diagnostic['cause']);
    }
}
