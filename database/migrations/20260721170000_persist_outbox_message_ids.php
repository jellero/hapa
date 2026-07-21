<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class LegacyOutboxMessageIdMigrationException extends RuntimeException
{
}

final class PersistOutboxMessageIds extends AbstractMigration
{
    private const DNS_NAMESPACE = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    public function up(): void
    {
        $this->execute('ALTER TABLE outbox_messages ADD COLUMN message_id UUID NULL');

        /** @var list<array{id: int|string, idempotency_key: string}> $messages */
        $messages = $this->fetchAll('SELECT id, idempotency_key FROM outbox_messages ORDER BY id');
        foreach ($messages as $message) {
            $this->execute(sprintf(
                "UPDATE outbox_messages SET message_id = '%s' WHERE id = %d",
                $this->legacyMessageId($message['idempotency_key']),
                (int) $message['id'],
            ));
        }

        $this->execute('ALTER TABLE outbox_messages ALTER COLUMN message_id SET NOT NULL');
        $this->execute('CREATE UNIQUE INDEX outbox_messages_message_id_unique ON outbox_messages (message_id)');
    }

    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS outbox_messages_message_id_unique');
        $this->execute('ALTER TABLE outbox_messages DROP COLUMN IF EXISTS message_id');
    }

    private function legacyMessageId(string $idempotencyKey): string
    {
        $namespace = hex2bin(str_replace('-', '', self::DNS_NAMESPACE));
        if ($namespace === false) {
            throw new LegacyOutboxMessageIdMigrationException('Namespace UUID legacy non valido.');
        }

        // The legacy SHA-1 is used only once to preserve IDs already emitted;
        // all newly appended messages use the SHA-256 UUIDv8 generator.
        $bytes = hex2bin(substr(sha1($namespace . 'hapa:outbox:' . trim($idempotencyKey)), 0, 32)); // NOSONAR
        if ($bytes === false) {
            throw new LegacyOutboxMessageIdMigrationException('Impossibile migrare il message ID legacy.');
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
