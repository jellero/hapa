<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

use Hapa\Core\Configuration\EnvironmentReader;
use Hapa\Core\Exception\HapaRuntimeException;

final class PiiKeyProvider
{
    private const LOCAL_KEY_SEED = 'hapa-local-development-pii-key-not-for-production';

    public static function passphrase(): string
    {
        return base64_encode(self::rawKey());
    }

    public static function rawKey(): string
    {
        $environment = strtolower(EnvironmentReader::value('APP_ENV', 'development'));
        $key = EnvironmentReader::secret('HAPA_PII_KEY', '');
        if ($key === '') {
            if ($environment === 'production') {
                throw new HapaRuntimeException(
                    'HAPA_PII_KEY_FILE o HAPA_PII_KEY deve contenere una chiave base64 di 32 byte in produzione.',
                );
            }

            return hash('sha256', self::LOCAL_KEY_SEED, true);
        }

        $decoded = base64_decode($key, true);
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new HapaRuntimeException('HAPA_PII_KEY deve essere una chiave base64 di 32 byte.');
        }

        return $decoded;
    }

    public static function keyId(): string
    {
        $environment = strtolower(EnvironmentReader::value('APP_ENV', 'development'));
        $keyId = trim(EnvironmentReader::value('HAPA_PII_KEY_ID', $environment === 'production' ? '' : 'local-v1'));
        if ($keyId === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,63}$/D', $keyId) !== 1) {
            throw new HapaRuntimeException('HAPA_PII_KEY_ID non valido.');
        }

        return $keyId;
    }
}
