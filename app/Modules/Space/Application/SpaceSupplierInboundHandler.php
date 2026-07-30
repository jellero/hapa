<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Application;

use DateTimeImmutable;
use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;
use PDO;

final readonly class SpaceSupplierInboundHandler implements InboundMessageHandler
{
    public function __construct(private PDO $connection)
    {
    }

    public function eventTypes(): array
    {
        return ['space.supplier.observed'];
    }

    public function handle(MessageEnvelope $message): void
    {
        if ($message->schemaVersion !== 1) {
            throw new InvalidArgumentException('Contratto anagrafica fornitori Space non supportato.');
        }
        $payload = $message->payload;
        $supplierId = self::requiredString($payload, 'id_fornitore', 64);
        $operation = self::requiredString($payload, 'operazione', 16);
        if (!in_array($operation, ['upsert', 'delete'], true)) {
            throw new InvalidArgumentException('Operazione fornitore Space non valida.');
        }
        $observedAt = new DateTimeImmutable(self::requiredString($payload, 'osservato_il', 100));
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO space_suppliers (
    space_supplier_id, legal_name, code, currency, delivery_days, precision_score,
    closing_order, city, state_id, province, postal_code, address, country,
    country_code, active, source_event_id, source_operation, source_observed_at,
    created_at, updated_at
) VALUES (
    :space_supplier_id, :legal_name, :code, :currency, :delivery_days, :precision_score,
    :closing_order, :city, :state_id, :province, :postal_code, :address, :country,
    :country_code, :active, :source_event_id, :source_operation, :source_observed_at,
    NOW(), NOW()
)
ON CONFLICT (space_supplier_id) DO UPDATE SET
    legal_name = COALESCE(EXCLUDED.legal_name, space_suppliers.legal_name),
    code = COALESCE(EXCLUDED.code, space_suppliers.code),
    currency = COALESCE(EXCLUDED.currency, space_suppliers.currency),
    delivery_days = COALESCE(EXCLUDED.delivery_days, space_suppliers.delivery_days),
    precision_score = COALESCE(EXCLUDED.precision_score, space_suppliers.precision_score),
    closing_order = COALESCE(EXCLUDED.closing_order, space_suppliers.closing_order),
    city = COALESCE(EXCLUDED.city, space_suppliers.city),
    state_id = COALESCE(EXCLUDED.state_id, space_suppliers.state_id),
    province = COALESCE(EXCLUDED.province, space_suppliers.province),
    postal_code = COALESCE(EXCLUDED.postal_code, space_suppliers.postal_code),
    address = COALESCE(EXCLUDED.address, space_suppliers.address),
    country = COALESCE(EXCLUDED.country, space_suppliers.country),
    country_code = COALESCE(EXCLUDED.country_code, space_suppliers.country_code),
    active = EXCLUDED.active,
    source_event_id = EXCLUDED.source_event_id,
    source_operation = EXCLUDED.source_operation,
    source_observed_at = EXCLUDED.source_observed_at,
    updated_at = NOW()
WHERE space_suppliers.source_observed_at IS NULL
   OR space_suppliers.source_observed_at <= EXCLUDED.source_observed_at
SQL);
        $statement->execute([
            'space_supplier_id' => $supplierId,
            'legal_name' => self::nullableString($payload, 'ragione_sociale', 255),
            'code' => self::nullableString($payload, 'codice', 100),
            'currency' => self::currency($payload['valuta'] ?? null),
            'delivery_days' => self::nullableInteger($payload, 'giorni_consegna'),
            'precision_score' => self::nullableInteger($payload, 'precisione'),
            'closing_order' => self::nullableString($payload, 'closing_order', 255),
            'city' => self::nullableString($payload, 'citta', 160),
            'state_id' => self::nullableScalar($payload, 'id_stato', 64),
            'province' => self::nullableString($payload, 'provincia', 80),
            'postal_code' => self::nullableScalar($payload, 'cap', 32),
            'address' => self::nullableString($payload, 'indirizzo', 10000),
            'country' => self::nullableString($payload, 'paese', 160),
            'country_code' => self::nullableString($payload, 'codice_paese', 8),
            'active' => $operation === 'upsert',
            'source_event_id' => self::nullableScalar($payload, 'id_evento', 64),
            'source_operation' => $operation,
            'source_observed_at' => $observedAt->format('Y-m-d H:i:s.uP'),
        ]);
    }

    /** @param array<string,mixed> $payload */
    private static function requiredString(array $payload, string $key, int $maximum): string
    {
        $value = $payload[$key] ?? null;
        if ((!is_string($value) && !is_int($value)) || trim((string) $value) === '' || strlen((string) $value) > $maximum) {
            throw new InvalidArgumentException('Campo fornitore Space non valido: ' . $key);
        }

        return trim((string) $value);
    }

    /** @param array<string,mixed> $payload */
    private static function nullableString(array $payload, string $key, int $maximum): ?string
    {
        $value = $payload[$key] ?? null;
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $maximum) : null;
    }

    /** @param array<string,mixed> $payload */
    private static function nullableScalar(array $payload, string $key, int $maximum): ?string
    {
        $value = $payload[$key] ?? null;
        return is_string($value) || is_int($value) ? mb_substr(trim((string) $value), 0, $maximum) ?: null : null;
    }

    /** @param array<string,mixed> $payload */
    private static function nullableInteger(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;
        return is_int($value) || (is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1) ? (int) $value : null;
    }

    private static function currency(mixed $value): ?string
    {
        $currency = is_string($value) ? strtoupper(trim($value)) : '';
        return preg_match('/^[A-Z]{3}$/D', $currency) === 1 ? $currency : null;
    }
}
