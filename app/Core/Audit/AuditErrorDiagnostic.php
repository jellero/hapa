<?php

declare(strict_types=1);

namespace Hapa\Core\Audit;

final class AuditErrorDiagnostic
{
    /** @var array<string, array{type: string, maximum?: int, required?: bool}> */
    private const SPACE_FIELDS = [
        'supplier' => ['type' => 'testo', 'maximum' => 200, 'required' => true],
        'external_item_id' => ['type' => 'testo', 'maximum' => 160, 'required' => true],
        'supplier_sku' => ['type' => 'testo', 'maximum' => 160, 'required' => true],
        'ean' => ['type' => 'testo', 'maximum' => 32],
        'name' => ['type' => 'testo', 'maximum' => 255],
        'description' => ['type' => 'testo', 'maximum' => 10000],
        'purchase_cost_minor' => ['type' => 'numero intero', 'required' => true],
        'currency' => ['type' => 'codice valuta ISO di 3 lettere', 'required' => true],
        'available_quantity' => ['type' => 'numero intero', 'required' => true],
        'source_version' => ['type' => 'testo', 'maximum' => 200, 'required' => true],
        'observed_at' => ['type' => 'data e ora ISO 8601', 'required' => true],
    ];

    /**
     * @param array<string, mixed>|null $after
     * @return array{message: string, field: ?string, expected: ?string, observed: ?string, value: ?string, cause: ?string}|null
     */
    public static function from(string $action, ?array $after): ?array
    {
        $message = self::string($after['error'] ?? null);
        if ($message === null) {
            return null;
        }

        $field = self::field($message);
        $payload = is_array($after['payload'] ?? null) ? $after['payload'] : [];
        $value = $field === null ? null : ($payload[$field] ?? null);
        $rule = $action === 'messaging.inbox_failed' && $field !== null
            ? (self::SPACE_FIELDS[$field] ?? null)
            : null;

        return [
            'message' => $message,
            'field' => $field,
            'expected' => $rule === null ? null : self::expected($rule),
            'observed' => $rule === null ? null : self::observed($value),
            'value' => self::displayValue($value),
            'cause' => $rule === null ? null : self::cause($value, $rule),
        ];
    }

    private static function field(string $message): ?string
    {
        return preg_match('/^Campo ([A-Za-z0-9_.-]+) (?:mancante o )?non valido(?::|\\.)/D', $message, $matches) === 1
            ? $matches[1]
            : null;
    }

    /** @param array{type: string, maximum?: int, required?: bool} $rule */
    private static function expected(array $rule): string
    {
        $parts = [$rule['type']];
        if (isset($rule['maximum'])) {
            $parts[] = 'massimo ' . $rule['maximum'] . ' byte';
        }
        $parts[] = ($rule['required'] ?? false) ? 'obbligatorio' : 'facoltativo';

        return implode(' · ', $parts);
    }

    private static function observed(mixed $value): string
    {
        if (!is_string($value)) {
            return $value === null ? 'campo assente o nullo' : 'tipo ' . get_debug_type($value);
        }

        return sprintf('%d caratteri · %d byte', mb_strlen($value), strlen($value));
    }

    /** @param array{type: string, maximum?: int, required?: bool} $rule */
    private static function cause(mixed $value, array $rule): ?string
    {
        if ($value === null) {
            return ($rule['required'] ?? false) ? 'Il campo obbligatorio non è presente.' : null;
        }
        if (($rule['type'] ?? '') === 'numero intero' && !is_int($value)) {
            return 'È stato ricevuto un valore di tipo ' . get_debug_type($value) . ' invece di un numero intero.';
        }
        if (!is_string($value)) {
            return 'È stato ricevuto un valore di tipo ' . get_debug_type($value) . ' invece di testo.';
        }
        if ($value === '') {
            return 'Il testo ricevuto è vuoto.';
        }
        if (trim($value) !== $value) {
            return 'Il testo contiene spazi iniziali o finali non ammessi.';
        }
        if (isset($rule['maximum']) && strlen($value) > $rule['maximum']) {
            $encoding = self::hasEncodingArtifacts($value)
                ? ' Sono presenti anche indicatori di codifica alterata (per esempio Ã o Â).'
                : '';

            return sprintf(
                'Il valore occupa %d byte, oltre il limite di %d.%s',
                strlen($value),
                $rule['maximum'],
                $encoding,
            );
        }

        return null;
    }

    private static function displayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? get_debug_type($value) : $encoded;
    }

    private static function hasEncodingArtifacts(string $value): bool
    {
        return str_contains($value, 'Ã')
            || str_contains($value, 'Â')
            || str_contains($value, 'â€')
            || str_contains($value, '�');
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
