<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;
use PDO;
use Hapa\Core\Exception\HapaRuntimeException;

final readonly class MarketplaceOfferPublicationResultHandler implements InboundMessageHandler
{
    private const INVALID_FIELD = 'Campo %s non valido.';

    public function __construct(private PDO $pdo)
    {
    }

    public function eventTypes(): array
    {
        return ['marketplace.offer.published', 'marketplace.offer.failed'];
    }

    public function handle(MessageEnvelope $message): void
    {
        if ($message->schemaVersion !== 1 || !in_array($message->eventType, $this->eventTypes(), true)) {
            throw new InvalidArgumentException('Contratto esito offerta SellRapido non supportato.');
        }
        if (($message->payload['connector'] ?? null) !== 'sellrapido') {
            throw new InvalidArgumentException('Connettore esito offerta non valido.');
        }
        $offerId = $this->positiveId($message->payload, 'offer_id');
        $version = $this->positiveInteger($message->payload, 'offer_version');
        $published = $message->eventType === 'marketplace.offer.published';
        $remoteVersion = $this->optionalString($message->payload, 'remote_version', 160);
        $externalOfferId = $this->optionalString($message->payload, 'external_offer_id', 160);
        $reason = $this->optionalString($message->payload, 'reason', 1000);
        $statement = $this->pdo->prepare(<<<'SQL'
UPDATE marketplace_offers
SET status = :status,
    external_offer_id = COALESCE(:external_offer_id, external_offer_id),
    remote_version = COALESCE(:remote_version, remote_version),
    last_synced_at = CASE WHEN :published THEN NOW() ELSE last_synced_at END,
    last_error = :last_error,
    updated_at = NOW()
WHERE id = :id AND source_version = :source_version
SQL);
        $statement->execute([
            'status' => $published ? 'synced' : 'error',
            'external_offer_id' => $externalOfferId,
            'remote_version' => $remoteVersion,
            'published' => $published,
            'last_error' => $published ? null : ($reason ?? 'Pubblicazione SellRapido non riuscita.'),
            'id' => $offerId,
            'source_version' => $version,
        ]);
        if ($statement->rowCount() > 0) {
            return;
        }

        $exists = $this->pdo->prepare('SELECT source_version FROM marketplace_offers WHERE id = :id');
        $exists->execute(['id' => $offerId]);
        $currentVersion = $exists->fetchColumn();
        if ($currentVersion === false) {
            throw new HapaRuntimeException('Offerta HAPA indicata dall\'esito SellRapido non trovata.');
        }
        // Un esito di una versione precedente non deve far regredire lo stato corrente.
        if ((int) $currentVersion < $version) {
            throw new InvalidArgumentException('Versione esito SellRapido futura rispetto a HAPA.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function positiveId(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            throw new InvalidArgumentException(sprintf(self::INVALID_FIELD, $key));
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $payload */
    private function positiveInteger(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf(self::INVALID_FIELD, $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $key, int $maximum): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || trim($value) !== $value || strlen($value) > $maximum) {
            throw new InvalidArgumentException(sprintf(self::INVALID_FIELD, $key));
        }

        return $value;
    }
}
