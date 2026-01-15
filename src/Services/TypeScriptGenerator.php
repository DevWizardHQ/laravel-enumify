<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Services;

use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;

/**
 * Generates TypeScript code from PHP enum definitions.
 */
class TypeScriptGenerator
{
    public function __construct(
        private readonly bool $generateUnionTypes = true,
        private readonly bool $generateLabelMaps = true,
        private readonly bool $generateMethodMaps = true,
        private readonly string $localizationMode = 'none',
    ) {
        $allowedLocalizationModes = ['none', 'react', 'vue'];

        if (! in_array($this->localizationMode, $allowedLocalizationModes, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid localization mode "%s". Allowed values are: %s.',
                    $this->localizationMode,
                    implode(', ', $allowedLocalizationModes)
                )
            );
        }
    }

    /**
     * Check if the generator is in static mode (non-localized).
     */
    private function isStaticMode(): bool
    {
        return $this->localizationMode === 'none';
    }

    /**
     * Generate TypeScript content for an enum definition.
     */
    public function generate(EnumDefinition $enum): string
    {
        $lines = [];

        // Add file header
        $lines[] = '// AUTO-GENERATED — DO NOT EDIT MANUALLY';
        $lines[] = '';

        // Localizer imports (only if localization is enabled AND labels will be generated)
        if ($this->localizationMode !== 'none' && $this->generateLabelMaps && $enum->hasLabels()) {
            if ($this->localizationMode === 'react') {
                $lines[] = "import { useLocalizer } from '@devwizard/laravel-localizer-react';";
                $lines[] = '';
            } elseif ($this->localizationMode === 'vue') {
                $lines[] = "import { useLocalizer } from '@devwizard/laravel-localizer-vue';";
                $lines[] = '';
            }
        }

        // 1. Export const definition
        $lines = array_merge($lines, $this->generateConstDefinition($enum));
        $lines[] = '';

        // 2. Export type definition
        if ($this->generateUnionTypes) {
            $lines[] = "export type {$enum->name} =";
            $lines[] = "  typeof {$enum->name}[keyof typeof {$enum->name}];";
            $lines[] = '';
        }

        // 3. Export Utils object (methods)
        $lines = array_merge($lines, $this->generateUtilsObject($enum));
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Generate the const object definition.
     *
     * @return array<string>
     */
    private function generateConstDefinition(EnumDefinition $enum): array
    {
        $lines = [];
        $lines[] = "export const {$enum->name} = {";

        foreach ($enum->cases as $case) {
            $tsName = $case->getTypeScriptName();
            $tsValue = $this->formatValue($case->value ?? $case->name);
            $lines[] = "  {$tsName}: {$tsValue},";
        }

        $lines[] = '} as const;';

        return $lines;
    }

    /**
     * Generate the Utils object containing all methods.
     *
     * @return array<string>
     */
    private function generateUtilsObject(EnumDefinition $enum): array
    {
        $lines = [];
        $lines[] = '/**';
        $lines[] = " * {$enum->name} enum methods (PHP-style)";
        $lines[] = ' */';

        if (! $this->isStaticMode()) {
            // Generate as a Hook / Composable function
            $lines[] = "export function use{$enum->name}Utils() {";
            
            // Only add useLocalizer if labels will be generated
            if ($this->generateLabelMaps && $enum->hasLabels()) {
                $lines[] = '    const { __ } = useLocalizer();';
                $lines[] = '';
            }
            
            $lines[] = '    return {';
        } else {
            // Generate as a static object
            $lines[] = "export const {$enum->name}Utils = {";
        }

        // Generate label() method if labels exist
        if ($this->generateLabelMaps && $enum->hasLabels()) {
            $lines = array_merge($lines, $this->generateLabelMethod($enum));
        }

        // Generate custom methods from PHP
        if ($this->generateMethodMaps) {
            foreach ($enum->methods as $method) {
                $lines = array_merge($lines, $this->generateCustomMethod($enum, $method));
            }
        }

        // Generate options() method
        if (! $this->isStaticMode()) {
            // Hook / Composable: methods are indented 8 spaces
            $lines[] = "        options(): {$enum->name}[] {";
            $lines[] = "            return Object.values({$enum->name});";
            $lines[] = '        },';
        } else {
            // Static object: methods are indented 2 spaces for backward compatibility
            $lines[] = "  options(): {$enum->name}[] {";
            $lines[] = "    return Object.values({$enum->name});";
            $lines[] = '  },';
        }

        if (! $this->isStaticMode()) {
            $lines[] = '    };';
            $lines[] = '}';
        } else {
            $lines[] = '};';
        }

        return $lines;
    }

    /**
     * Generate the label() method.
     *
     * @return array<string>
     */
    private function generateLabelMethod(EnumDefinition $enum): array
    {
        $lines = [];

        if ($this->isStaticMode()) {
            $lines[] = "  label(status: {$enum->name}): string {";
            $lines[] = '    switch (status) {';
        } else {
            $lines[] = "        label(status: {$enum->name}): string {";
            $lines[] = '            switch (status) {';
        }

        foreach ($enum->cases as $case) {
            $label = $case->label ?? $this->humanize($case->name);
            $escapedLabel = $this->escapeString($label);

            $tsName = $case->getTypeScriptName();

            if ($this->isStaticMode()) {
                $lines[] = "      case {$enum->name}.{$tsName}:";
                $lines[] = "        return '{$escapedLabel}';";
            } else {
                $lines[] = "                case {$enum->name}.{$tsName}:";
                $lines[] = "                    return __('{$escapedLabel}');";
            }
        }

        if ($this->isStaticMode()) {
            $lines[] = '    }';
            $lines[] = '  },';
        } else {
            $lines[] = '            }';
            $lines[] = '        },';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Generate a custom method (color, isActive, etc.).
     *
     * @return array<string>
     */
    private function generateCustomMethod(EnumDefinition $enum, EnumMethodDefinition $method): array
    {
        $lines = [];
        $methodName = $method->name;
        $returnType = $method->getTypeScriptType();

        // Method signature indentation
        if ($this->isStaticMode()) {
            $lines[] = "  {$methodName}(status: {$enum->name}): {$returnType} {";
        } else {
            $lines[] = "        {$methodName}(status: {$enum->name}): {$returnType} {";
        }

        if ($method->isBooleanMethod()) {
            $trueCases = [];
            foreach ($method->values as $caseName => $val) {
                if ($val === true) {
                    $trueCases[] = $caseName;
                }
            }

            if ($this->isStaticMode()) {
                if (count($trueCases) === 1) {
                    $lines[] = "    return status === {$enum->name}.{$trueCases[0]};";
                } elseif (count($trueCases) === 0) {
                    $lines[] = '    return false;';
                } else {
                    $checks = array_map(fn ($c) => "status === {$enum->name}.{$c}", $trueCases);
                    $lines[] = '    return '.implode(' || ', $checks).';';
                }
            } else {
                if (count($trueCases) === 1) {
                    $lines[] = "            return status === {$enum->name}.{$trueCases[0]};";
                } elseif (count($trueCases) === 0) {
                    $lines[] = '            return false;';
                } else {
                    $checks = array_map(fn ($c) => "status === {$enum->name}.{$c}", $trueCases);
                    $lines[] = '            return '.implode(' || ', $checks).';';
                }
            }
        } else {
            if ($this->isStaticMode()) {
                $lines[] = '    switch (status) {';
                foreach ($enum->cases as $case) {
                    $val = $method->values[$case->name] ?? null;
                    $tsVal = $this->formatValue($val);

                    $tsName = $case->getTypeScriptName();
                    $lines[] = "      case {$enum->name}.{$tsName}:";
                    $lines[] = "        return {$tsVal};";
                }
                $lines[] = '    }';
            } else {
                $lines[] = '            switch (status) {';
                foreach ($enum->cases as $case) {
                    $val = $method->values[$case->name] ?? null;
                    $tsVal = $this->formatValue($val);

                    $tsName = $case->getTypeScriptName();
                    $lines[] = "                case {$enum->name}.{$tsName}:";
                    $lines[] = "                    return {$tsVal};";
                }
                $lines[] = '            }';
            }
        }

        if ($this->isStaticMode()) {
            $lines[] = '  },';
        } else {
            $lines[] = '        },';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * Format a PHP value for TypeScript output.
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'".$this->escapeString($value)."'";
        }

        return 'null';
    }

    /**
     * Convert SCREAMING_SNAKE_CASE to human-readable text.
     */
    private function humanize(string $value): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $value)));
    }

    /**
     * Escape a string for use in TypeScript.
     */
    private function escapeString(string $value): string
    {
        return str_replace(
            ['\\', "'", "\n", "\r", "\t"],
            ['\\\\', "\'", '\n', '\r', '\t'],
            $value
        );
    }

    /**
     * Generate barrel index file content.
     *
     * @param  array<EnumDefinition>  $enums
     */
    public function generateBarrel(array $enums, string $fileCase): string
    {
        $lines = [];
        $lines[] = '// AUTO-GENERATED — DO NOT EDIT MANUALLY';
        $lines[] = '';

        foreach ($enums as $enum) {
            $filename = $enum->getFilename($fileCase);
            $lines[] = "export * from './{$filename}';";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
