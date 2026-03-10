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
     * Requires the enum to implement a public instance method with that name.
     *
     * @return array<string|int, string>
     */
    public static function options(string $label = 'label'): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->{$label}()])
            ->all();
    }

    /**
     * Get all enum cases as an array of {value, label} objects for frontend selects.
     *
     * @return array<int, array{value: string|int, label: string}>
     */
    public static function selectOptions(string $label = 'label'): array
    {
        return collect(self::cases())
            ->map(fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->{$label}(),
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
        return in_array($value, self::values(), true);
    }
}
