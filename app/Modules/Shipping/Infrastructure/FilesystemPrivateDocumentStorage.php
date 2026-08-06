<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Infrastructure;

use Hapa\Core\Exception\HapaRuntimeException;
use Hapa\Core\Security\PiiKeyProvider;
use Hapa\Modules\Shipping\Contract\PrivateDocumentStorage;
use Hapa\Modules\Shipping\Contract\StoredDocument;
use InvalidArgumentException;
use JsonException;

final readonly class FilesystemPrivateDocumentStorage implements PrivateDocumentStorage
{
    private const ENVELOPE_PREFIX = "HAPA-PII-FILE-V1\n";
    private const CIPHER = 'aes-256-gcm';

    private string $root;

    public function __construct(string $root, private int $maximumBytes = 10_485_760)
    {
        if ($maximumBytes < 1) {
            throw new InvalidArgumentException('Dimensione massima documento non valida.');
        }
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) {
            throw new HapaRuntimeException('Impossibile creare lo storage documenti privato.');
        }
        $resolved = realpath($root);
        if ($resolved === false) {
            throw new HapaRuntimeException('Impossibile risolvere lo storage documenti privato.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function store(string $scope, string $format, string $content): StoredDocument
    {
        $scope = strtolower(trim($scope));
        $format = strtolower(trim($format));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/D', $scope) !== 1) {
            throw new InvalidArgumentException('Ambito documento non valido.');
        }
        if (!in_array($format, ['pdf', 'zpl', 'png'], true)) {
            throw new InvalidArgumentException('Formato documento non consentito.');
        }
        $bytes = strlen($content);
        if ($bytes < 1 || $bytes > $this->maximumBytes) {
            throw new InvalidArgumentException('Dimensione documento non consentita.');
        }

        $relativeDirectory = sprintf('%s/%s', $scope, gmdate('Y/m'));
        $directory = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new HapaRuntimeException('Impossibile creare la directory documento.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $format;
        $reference = $relativeDirectory . '/' . $name;
        $destination = $directory . DIRECTORY_SEPARATOR . $name;
        $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
        $encrypted = $this->encrypt($content, $reference);
        $written = file_put_contents($temporary, $encrypted, LOCK_EX);
        if ($written !== strlen($encrypted)) {
            @unlink($temporary);
            throw new HapaRuntimeException('Scrittura documento incompleta.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new HapaRuntimeException('Pubblicazione atomica del documento fallita.');
        }

        return new StoredDocument($reference, hash('sha256', $content), $bytes, strtoupper($format));
    }

    public function read(string $reference, string $expectedChecksum): string
    {
        $path = $this->resolve($reference);
        $stored = file_get_contents($path);
        if (!is_string($stored)) {
            throw new HapaRuntimeException('Documento privato non leggibile.');
        }
        $content = str_starts_with($stored, self::ENVELOPE_PREFIX)
            ? $this->decrypt(substr($stored, strlen(self::ENVELOPE_PREFIX)), $reference)
            : $stored;
        if (preg_match('/^[0-9a-f]{64}$/D', $expectedChecksum) !== 1
            || !hash_equals($expectedChecksum, hash('sha256', $content))) {
            throw new HapaRuntimeException('Checksum documento non valido.');
        }

        return $content;
    }

    public function delete(string $reference): void
    {
        $path = $this->resolve($reference);
        if (!unlink($path)) {
            throw new HapaRuntimeException('Impossibile eliminare il documento privato.');
        }
    }

    private function encrypt(string $content, string $reference): string
    {
        $nonceLength = openssl_cipher_iv_length(self::CIPHER);
        if ($nonceLength < 12) {
            throw new HapaRuntimeException('Cifrario documenti privati non disponibile.');
        }
        $nonce = random_bytes($nonceLength);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $content,
            self::CIPHER,
            PiiKeyProvider::rawKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $reference,
            16,
        );
        if (!is_string($ciphertext)) {
            throw new HapaRuntimeException('Cifratura documento privato fallita.');
        }
        try {
            $envelope = json_encode([
                'version' => 1,
                'key_id' => PiiKeyProvider::keyId(),
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($ciphertext),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new HapaRuntimeException('Serializzazione documento cifrato fallita.', 0, $exception);
        }

        return self::ENVELOPE_PREFIX . $envelope;
    }

    private function decrypt(string $envelope, string $reference): string
    {
        try {
            $payload = json_decode($envelope, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HapaRuntimeException('Documento cifrato non valido.', 0, $exception);
        }
        if (!is_array($payload)
            || ($payload['version'] ?? null) !== 1
            || ($payload['key_id'] ?? null) !== PiiKeyProvider::keyId()) {
            throw new HapaRuntimeException('Versione o chiave del documento cifrato non supportata.');
        }
        $nonce = base64_decode((string) ($payload['nonce'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $ciphertext = base64_decode((string) ($payload['ciphertext'] ?? ''), true);
        if (!is_string($nonce) || !is_string($tag) || !is_string($ciphertext)) {
            throw new HapaRuntimeException('Envelope del documento cifrato non valido.');
        }
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            PiiKeyProvider::rawKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $reference,
        );
        if (!is_string($plaintext)) {
            throw new HapaRuntimeException('Autenticazione del documento cifrato fallita.');
        }

        return $plaintext;
    }

    private function resolve(string $reference): string
    {
        if (preg_match('#^[a-z0-9][a-z0-9_-]{1,63}/\d{4}/\d{2}/[0-9a-f]{32}\.(pdf|zpl|png)$#D', $reference) !== 1) {
            throw new InvalidArgumentException('Riferimento documento non valido.');
        }
        $candidate = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference);
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR) || !is_file($resolved)) {
            throw new HapaRuntimeException('Documento privato non trovato.');
        }

        return $resolved;
    }
}
