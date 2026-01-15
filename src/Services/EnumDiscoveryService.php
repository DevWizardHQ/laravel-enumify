<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Services;

use DevWizardHQ\Enumify\Contracts\HasLabels;
use DevWizardHQ\Enumify\Data\EnumCaseDefinition;
use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;
use Illuminate\Support\Facades\File;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * Discovers PHP enums from configured directories.
 */
class EnumDiscoveryService
{
    /**
     * Supported return types for method maps.
     */
    private const SUPPORTED_METHOD_TYPES = [
        'string',
        'int',
        'float',
        'bool',
        'null',
    ];

    /**
     * Methods to exclude from custom method detection.
     */
    private const EXCLUDED_METHODS = [
        'cases',
        'from',
        'tryFrom',
        'label',  // handled separately as labels
        'labels',  // handled separately as labels
    ];

    /**
     * Discover all enums from the given paths.
     *
     * @param  array<string>  $paths  Directories to scan (relative to base_path)
     * @param  array<string>  $include  FQCN patterns to include (empty = all)
     * @param  array<string>  $exclude  FQCN patterns to exclude
     * @return array<EnumDefinition>
     */
    public function discover(array $paths, array $include = [], array $exclude = []): array
    {
        $enums = [];

        foreach ($paths as $path) {
            // Support both absolute paths (for testing) and relative paths
            $absolutePath = $this->isAbsolutePath($path)
                ? $path
                : base_path($path);

            if (! File::isDirectory($absolutePath)) {
                continue;
            }

            $files = File::allFiles($absolutePath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $fqcn = $this->extractFqcn($file->getRealPath());

                if ($fqcn === null) {
                    continue;
                }

                if (! $this->isEnum($fqcn)) {
                    continue;
                }

                if (! $this->matchesFilters($fqcn, $include, $exclude)) {
                    continue;
                }

                $definition = $this->buildDefinition($fqcn);

                if ($definition !== null) {
                    $enums[] = $definition;
                }
            }
        }

        // Sort by FQCN for deterministic output
        usort($enums, fn (EnumDefinition $a, EnumDefinition $b) => strcmp($a->fqcn, $b->fqcn));

        return $enums;
    }

    /**
     * Determine if the path is absolute (supports Windows drive letters).
     */
    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     */
    private function extractFqcn(string $filePath): ?string
    {
        $contents = File::get($filePath);

        // Extract namespace
        if (! preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch)) {
            return null;
        }

        $namespace = trim($namespaceMatch[1]);

        // Extract enum name - must be at the start of a line (handles comments containing "enum")
        // Matches: enum Name or enum Name: type
        if (! preg_match('/^\s*enum\s+(\w+)/m', $contents, $enumMatch)) {
            return null;
        }

        $enumName = trim($enumMatch[1]);

        return $namespace.'\\'.$enumName;
    }

    /**
     * Check if the given FQCN is a valid enum.
     */
    private function isEnum(string $fqcn): bool
    {
        if (! class_exists($fqcn) && ! enum_exists($fqcn)) {
            return false;
        }

        return enum_exists($fqcn);
    }

    /**
     * Check if the enum matches the include/exclude filters.
     */
    private function matchesFilters(string $fqcn, array $include, array $exclude): bool
    {
        // Check exclude patterns first
        foreach ($exclude as $pattern) {
            if ($this->matchesPattern($fqcn, $pattern)) {
                return false;
            }
        }

        // If no include patterns, include everything
        if (empty($include)) {
            return true;
        }

        // Check include patterns
        foreach ($include as $pattern) {
            if ($this->matchesPattern($fqcn, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the FQCN matches a glob-like pattern.
     */
    private function matchesPattern(string $fqcn, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = str_replace(
            ['\*\*', '\*'],
            ['.*', '[^\\\\]*'],
            preg_quote($pattern, '/')
        );

        return (bool) preg_match('/^'.$regex.'$/', $fqcn);
    }

    /**
     * Build an EnumDefinition from a fully qualified class name.
     */
    private function buildDefinition(string $fqcn): ?EnumDefinition
    {
        try {
            $reflection = new ReflectionEnum($fqcn);
        } catch (\ReflectionException) {
            return null;
        }

        $isBacked = $reflection->isBacked();
        $backingType = null;

        if ($isBacked) {
            $backingType = $reflection->getBackingType()?->getName();
        }

        $cases = [];
        $staticLabels = $this->getStaticLabels($reflection);
        $enumCases = $reflection->getCases();

        foreach ($enumCases as $case) {
            $caseName = $case->getName();
            $caseValue = ($isBacked && $case instanceof ReflectionEnumBackedCase)
                ? $case->getBackingValue()
                : null;
            $caseInstance = $case->getValue();

            // Try to get label
            $label = $this->getCaseLabel($caseInstance, $staticLabels);

            $cases[] = new EnumCaseDefinition(
                name: $caseName,
                value: $caseValue,
                label: $label,
            );
        }

        // Discover custom methods
        $methods = $this->discoverMethods($reflection, $enumCases);

        return new EnumDefinition(
            fqcn: $fqcn,
            name: $reflection->getShortName(),
            isBacked: $isBacked,
            backingType: $backingType,
            cases: $cases,
            methods: $methods,
        );
    }

    /**
     * Discover custom methods on the enum.
     *
     * @param  array<\ReflectionEnumUnitCase|\ReflectionEnumBackedCase>  $enumCases
     * @return array<EnumMethodDefinition>
     */
    private function discoverMethods(ReflectionEnum $reflection, array $enumCases): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip excluded methods
            if (in_array($method->getName(), self::EXCLUDED_METHODS, true)) {
                continue;
            }

            // Skip static methods
            if ($method->isStatic()) {
                continue;
            }

            // Skip methods with parameters
            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            // Get return type
            $returnTypes = $this->resolveReturnTypes($method->getReturnType());
            if ($returnTypes === null) {
                continue;
            }

            // Get values for each case
            $values = [];
            foreach ($enumCases as $case) {
                $caseInstance = $case->getValue();

                try {
                    $values[$case->getName()] = $method->invoke($caseInstance);
                } catch (\Throwable) {
                    // Skip methods that throw exceptions
                    continue 2;
                }
            }

            $methods[] = new EnumMethodDefinition(
                name: $method->getName(),
                returnTypes: $returnTypes,
                typescriptType: $this->resolveTypeScriptType($returnTypes),
                values: $values,
            );
        }

        // Sort by method name for deterministic output
        usort($methods, fn (EnumMethodDefinition $a, EnumMethodDefinition $b) => strcmp($a->name, $b->name));

        return $methods;
    }

    /**
     * Resolve supported return types from a method return type definition.
     *
     * @return array<string>|null
     */
    private function resolveReturnTypes(?ReflectionType $returnType): ?array
    {
        if ($returnType === null) {
            return null;
        }

        $types = [];

        if ($returnType instanceof ReflectionNamedType) {
            $types[] = $this->normalizeTypeName($returnType->getName());

            if ($returnType->allowsNull() && $returnType->getName() !== 'null') {
                $types[] = 'null';
            }
        } elseif ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if (! $type instanceof ReflectionNamedType) {
                    return null;
                }

                $types[] = $this->normalizeTypeName($type->getName());
            }
        } else {
            return null;
        }

        $types = array_values(array_unique($types));

        foreach ($types as $type) {
            if (! in_array($type, self::SUPPORTED_METHOD_TYPES, true)) {
                return null;
            }
        }

        return $types;
    }

    /**
     * Normalize PHP type names.
     */
    private function normalizeTypeName(string $type): string
    {
        return match ($type) {
            'boolean' => 'bool',
            'integer' => 'int',
            default => $type,
        };
    }

    /**
     * Resolve TypeScript union type for a set of PHP types.
     */
    private function resolveTypeScriptType(array $returnTypes): string
    {
        $tsTypes = [];

        if (in_array('string', $returnTypes, true)) {
            $tsTypes[] = 'string';
        }

        if (in_array('int', $returnTypes, true) || in_array('float', $returnTypes, true)) {
            $tsTypes[] = 'number';
        }

        if (in_array('bool', $returnTypes, true)) {
            $tsTypes[] = 'boolean';
        }

        if (in_array('null', $returnTypes, true)) {
            $tsTypes[] = 'null';
        }

        return implode(' | ', $tsTypes);
    }

    /**
     * Get labels from static labels() method if available.
     *
     * @return array<string, string>|null
     */
    private function getStaticLabels(ReflectionEnum $reflection): ?array
    {
        if (! $reflection->hasMethod('labels')) {
            return null;
        }

        $method = $reflection->getMethod('labels');

        if (! $method->isStatic() || ! $method->isPublic()) {
            return null;
        }

        try {
            $enumClass = $reflection->getName();
            /** @var array<string|int, string>|mixed $labels */
            $labels = $enumClass::labels();  // @phpstan-ignore staticMethod.notFound

            if (is_array($labels)) {
                $normalized = [];
                foreach ($labels as $key => $value) {
                    $normalized[(string) $key] = $value;
                }

                return $normalized;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Get the label for a specific enum case.
     */
    private function getCaseLabel(UnitEnum $case, ?array $staticLabels): ?string
    {
        // First try per-case label() method (HasLabels interface)
        if ($case instanceof HasLabels) {
            try {
                return $case->label();
            } catch (\Throwable) {
                // Fall through
            }
        }

        // Check if enum has a label() method (even without interface)
        if (method_exists($case, 'label')) {
            try {
                $label = $case->label();
                if (is_string($label)) {
                    return $label;
                }
            } catch (\Throwable) {
                // Fall through
            }
        }

        // Try static labels map
        if ($staticLabels !== null && isset($staticLabels[$case->name])) {
            return $staticLabels[$case->name];
        }

        return null;
    }
}
