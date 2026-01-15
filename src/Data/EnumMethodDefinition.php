<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Data;

/**
 * Represents a custom method defined on a PHP enum.
 */
final readonly class EnumMethodDefinition
{
    /**
     * @param  string  $name  Method name (e.g., 'color')
     * @param  array<string>  $returnTypes  Normalized PHP return types
     * @param  string  $typescriptType  TypeScript union type for the method map
     * @param  array<string, mixed>  $values  Map of case name => return value
     */
    public function __construct(
        public string $name,
        public array $returnTypes,
        public string $typescriptType,
        public array $values,
    ) {}

    /**
     * Get the TypeScript type for this method's return value.
     */
    public function getTypeScriptType(): string
    {
        return $this->typescriptType;
    }

    /**
     * Check if this method returns a boolean (for guard functions).
     */
    public function isBooleanMethod(): bool
    {
        return $this->returnTypes === ['bool'];
    }
}
