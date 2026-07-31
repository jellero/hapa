<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Application;

final class CatalogPublicationRuleMatcher
{
    /**
     * @param array<string, mixed> $product
     * @param list<array<string, mixed>> $rules Ordered by priority, exclusion first, then id.
     */
    public static function allows(array $product, int $marketplaceId, array $rules): bool
    {
        $winningPriority = null;
        $included = false;
        foreach ($rules as $rule) {
            if ($rule['marketplace_id'] !== null && (int) $rule['marketplace_id'] !== $marketplaceId) {
                continue;
            }
            if (!self::matches($product, $rule)) {
                continue;
            }
            $priority = (int) $rule['priority'];
            if ($winningPriority !== null && $priority > $winningPriority) {
                break;
            }
            $winningPriority ??= $priority;
            if ((string) $rule['action'] === 'exclude') {
                return false;
            }
            $included = true;
        }

        return $included;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $rule
     */
    public static function matches(array $product, array $rule): bool
    {
        $field = (string) $rule['field'];
        $actualValues = $field === 'supplier_id'
            ? [$product['supplier_code'] ?? null, $product['supplier_id'] ?? null, $product['supplier_name'] ?? null]
            : [$field === 'available_quantity' ? ($product[$field] ?? 0) : ($product[$field] ?? null)];
        $expected = (string) $rule['match_value'];
        $operator = (string) $rule['operator'];
        foreach ($actualValues as $actual) {
            if (self::valueMatches($actual, $operator, $expected)) {
                return true;
            }
        }

        return false;
    }

    private static function valueMatches(mixed $actual, string $operator, string $expected): bool
    {
        if (in_array($operator, ['minimum', 'maximum'], true)) {
            if (!is_int($actual) && !is_numeric($actual)) {
                return false;
            }

            return $operator === 'minimum'
                ? (int) $actual >= (int) $expected
                : (int) $actual <= (int) $expected;
        }
        $actual = mb_strtolower(trim((string) $actual));
        $expected = mb_strtolower(trim($expected));

        return match ($operator) {
            'equals' => $actual === $expected,
            'starts_with' => str_starts_with($actual, $expected),
            'ends_with' => str_ends_with($actual, $expected),
            default => str_contains($actual, $expected),
        };
    }
}
