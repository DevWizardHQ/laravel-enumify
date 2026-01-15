<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Data;

/**
 * Represents a single case within a PHP enum.
 */
final readonly class EnumCaseDefinition
{
    /**
     * @param  string  $name  Original PHP case name (e.g., PENDING_PAYMENT)
     * @param  string|int|null  $value  Backed value or null for UnitEnum
     * @param  string|null  $label  Human-readable label if available
     */
    public function __construct(
        public string $name,
        public string|int|null $value,
        public ?string $label = null,
    ) {}

    /**
     * Get the TypeScript-friendly name (PascalCase).
     */
    public function getTypeScriptName(): string
    {
        // Convert SCREAMING_SNAKE_CASE to PascalCase
        $parts = explode('_', strtolower($this->name));

        return implode('', array_map(ucfirst(...), $parts));
    }

    /**
     * Get the TypeScript value representation.
     */
    public function getTypeScriptValue(): string
    {
        if ($this->value === null) {
            // UnitEnum: use case name as string value
            return '"'.$this->name.'"';
        }

        if (is_int($this->value)) {
            return (string) $this->value;
        }

        return '"'.$this->value.'"';
    }
}
