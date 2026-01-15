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
     * Get the TypeScript-friendly name.
     * Preserves the original casing from PHP enum case names.
     */
    public function getTypeScriptName(): string
    {
        // Simply return the name as-is to preserve the original casing
        return $this->name;
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
