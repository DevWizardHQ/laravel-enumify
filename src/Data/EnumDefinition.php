<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Data;

/**
 * Represents metadata about a discovered PHP enum.
 */
final readonly class EnumDefinition
{
    /**
     * @param  string  $fqcn  Fully qualified class name
     * @param  string  $name  Short class name
     * @param  bool  $isBacked  Whether the enum is a BackedEnum
     * @param  string|null  $backingType  'string' | 'int' | null for UnitEnum
     * @param  array<EnumCaseDefinition>  $cases  Enum cases with metadata
     * @param  array<EnumMethodDefinition>  $methods  Custom methods with return values
     */
    public function __construct(
        public string $fqcn,
        public string $name,
        public bool $isBacked,
        public ?string $backingType,
        public array $cases,
        public array $methods = [],
    ) {}

    /**
     * Get the output filename based on the naming convention.
     */
    public function getFilename(string $fileCase): string
    {
        return match ($fileCase) {
            'camel' => $this->toCamelCase($this->name),
            'pascal' => $this->name,
            default => $this->toKebabCase($this->name),
        };
    }

    /**
     * Check if the enum has any labels defined.
     */
    public function hasLabels(): bool
    {
        foreach ($this->cases as $case) {
            if ($case->label !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the enum has any custom methods.
     */
    public function hasMethods(): bool
    {
        return ! empty($this->methods);
    }

    private function toKebabCase(string $value): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', $value));
    }

    private function toCamelCase(string $value): string
    {
        return lcfirst($value);
    }
}
