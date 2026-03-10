<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Concerns;

/**
 * Provides common utility methods for backed enums.
 *
 * Use this trait on any string-backed or int-backed enum to gain
 * helpers for listing options, values, names, and checking existence.
 *
 * @mixin \BackedEnum
 */
trait EnumHelpers
{
    /**
     * Get all enum cases as an array of value => label pairs.
     *
     * Calls the given method name on each case (defaults to "label").
     * Falls back to a humanized version of the case name when the method does not exist.
     *
     * Note: PHP coerces numeric-string keys to integers in arrays.
     * Use {@see selectOptions()} if your enum has numeric-like string values.
     *
     * @return array<string|int, string>
     */
    public static function options(string $label = 'label'): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [
                $case->value => method_exists($case, $label)
                    ? $case->{$label}()
                    : self::humanize($case->name),
            ])
            ->all();
    }

    /**
     * Get all enum cases as an array of {value, label} objects for frontend selects.
     *
     * Falls back to a humanized version of the case name when the method does not exist.
     *
     * @return array<int, array{value: string|int, label: string}>
     */
    public static function selectOptions(string $label = 'label'): array
    {
        return collect(self::cases())
            ->map(fn (self $case): array => [
                'value' => $case->value,
                'label' => method_exists($case, $label)
                    ? $case->{$label}()
                    : self::humanize($case->name),
            ])
            ->values()
            ->all();
    }

    /**
     * Get all enum values as an array.
     *
     * @return array<int, string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all enum names as an array.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Check if a value exists in this enum.
     */
    public static function hasValue(string|int $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /**
     * Convert a SCREAMING_SNAKE_CASE name to a human-readable title.
     */
    private static function humanize(string $name): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $name)));
    }
}
