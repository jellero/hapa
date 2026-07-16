<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use InvalidArgumentException;

final class DeterministicMessageId
{
    private const DNS_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public static function fromIdempotencyKey(string $idempotencyKey): string
    {
        $normalized = trim($idempotencyKey);
        if ($normalized === '') {
            throw new InvalidArgumentException('La chiave di idempotenza è obbligatoria.');
        }

        $namespace = hex2bin(str_replace('-', '', self::DNS_NAMESPACE));
        if ($namespace === false) {
            throw new InvalidArgumentException('Namespace UUID non valido.');
        }

        $hash = sha1($namespace . 'hapa:outbox:' . $normalized);
        $bytes = hex2bin(substr($hash, 0, 32));
        if ($bytes === false) {
            throw new InvalidArgumentException('Impossibile generare il message ID.');
        }

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
