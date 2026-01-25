<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionEnum;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Enum Refactoring Command
 *
 * Scans, detects, and refactors hardcoded enum values to use proper enum references.
 * Can also normalize enum keys to UPPERCASE and fix all references.
 */
final class RefactorCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'enumify:refactor
        {--f|fix : Apply refactoring changes to files}
        {--d|dry-run : Preview changes without applying}
        {--e|enum= : Target specific enum class (short name, e.g., InvoiceStatus)}
        {--p|path= : Limit scan to specific directory}
        {--i|interactive : Interactive mode with guided prompts}
        {--j|json : Output results as JSON}
        {--backup : Create backup before applying changes}
        {--include=* : File patterns to include (e.g., *.php)}
        {--exclude=* : Paths/patterns to exclude}
        {--strict : Strict matching (requires column name to match enum context)}
        {--report= : Export report to file (formats: json, csv, md)}
        {--detailed : Show detailed output with code context}
        {--normalize-keys : Convert enum keys to UPPERCASE and fix all references}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and refactor hardcoded enum values. Supports dry-run, interactive mode, key normalization, and comprehensive reporting.';

    /**
     * @var array<string, array{name: string, cases: array<string, string>, class: string, path: string}>
     */
    private array $enums = [];

    /**
     * @var array<int, array{file: string, line: int, type: string, column: string, value: string, code: string, enum: string, case: string, class: string, context: string}>
     */
    private array $issues = [];

    /**
     * @var array<int, array{enum: string, oldKey: string, newKey: string, file: string, references: array<array{file: string, line: int, code: string}>}>
     */
    private array $keyNormalizationIssues = [];

    /**
     * @var array<string, string>
     */
    private array $backups = [];

    /**
     * Patterns to detect hardcoded enum values.
     *
     * @var array<string, string>
     */
    private array $patterns = [
        'where' => '/->where\([\'"](\w+)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\)/',
        'orWhere' => '/->orWhere\([\'"](\w+)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\)/',
        'whereNot' => '/->whereNot\([\'"](\w+)[\'"]\s*,\s*[\'"]([a-zA-Z0-9_-]+)[\'"]\)/',
        'update' => '/->update\(\[[\'"](\w+)[\'"]\s*=>\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/',
        'create' => '/->create\(\[[\'"](\w+)[\'"]\s*=>\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/',
        'array' => '/[\'"](status|type|state|category|priority|role|level|method|direction|source|channel)[\'"]\s*=>\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/',
        'comparison' => '/\$\w+->(status|type|state|category|priority|role|level)\s*===?\s*[\'"]([a-zA-Z0-9_-]+)[\'"]/',
        'validation' => '/Rule::in\(\[([^\]]+)\]\)/',
    ];

    /**
     * Default paths to exclude from scanning.
     *
     * @var array<int, string>
     */
    private array $defaultExcludes = [
        'vendor',
        'node_modules',
        'storage',
        '.git',
        'bootstrap/cache',
        'public',
    ];

    public function handle(): int
    {
        // Handle interactive mode first
        if ($this->option('interactive')) {
            return $this->runInteractive();  // @codeCoverageIgnore
        }

        // Handle normalize-keys mode
        if ($this->option('normalize-keys')) {
            return $this->runNormalizeKeys();
        }

        // Determine mode
        $isFix = $this->option('fix');
        $isDryRun = $this->option('dry-run');

        $this->displayBanner();
        $this->loadEnums();

        if (count($this->enums) === 0) {
            $this->components->error('No enums found. Check your enumify.paths.enums configuration.');

            return self::FAILURE;
        }

        // --fix applies changes, --dry-run previews changes, default is scan
        if ($isFix) {
            return $this->fix(dryRun: false);
        }

        if ($isDryRun) {
            return $this->fix(dryRun: true);
        }

        // Default: scan mode
        return $this->scan();
    }

    /**
     * Display the command banner.
     */
    private function displayBanner(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>╔═══════════════════════════════════════════════════════════════╗</>');
        $this->line('<fg=cyan>║</> <fg=white;options=bold>🔧 Enumify Refactor</> <fg=gray>— Hardcoded Value Scanner & Refactorer</> <fg=cyan>║</>');
        $this->line('<fg=cyan>╚═══════════════════════════════════════════════════════════════╝</>');
        $this->newLine();
    }

    /**
     * Run in interactive mode using Laravel Prompts.
     *
     * @codeCoverageIgnore
     */
    private function runInteractive(): int
    {
        $this->displayBanner();
        $this->loadEnums();

        if (count($this->enums) === 0) {
            $this->components->error('No enums found. Check your enumify.paths.enums configuration.');

            return self::FAILURE;
        }

        // Select mode
        $mode = select(
            label: 'What would you like to do?',
            options: [
                'scan' => '🔍 Scan — Find hardcoded enum values',
                'fix' => '👁️ Preview — Show what would change (dry-run)',
                'apply' => '✏️ Apply — Refactor hardcoded values',
                'normalize' => '🔠 Normalize — Convert enum keys to UPPERCASE',
            ],
            default: 'scan',
            hint: 'Use arrow keys to navigate, Enter to select'
        );

        if ($mode === 'normalize') {
            return $this->runNormalizeKeysInteractive();
        }

        // Select enums to target
        $enumOptions = [];
        foreach ($this->enums as $class => $data) {
            $caseCount = count($data['cases']);
            $enumOptions[$data['name']] = sprintf('%s (%d cases)', $data['name'], $caseCount);
        }

        $selectedEnums = multiselect(
            label: 'Which enums should be checked?',
            options: $enumOptions,
            default: array_keys($enumOptions),
            hint: 'Space to toggle, Enter to confirm.'
        );

        // Get path
        $path = text(
            label: 'Directory to scan',
            placeholder: 'app',
            default: 'app',
            hint: 'Relative to project root (e.g., app, app/Models)'
        );

        // Convert relative to absolute
        $fullPath = base_path($path);
        if (! is_dir($fullPath)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        // Run scan
        $this->scanDirectory($fullPath, $selectedEnums);

        if (empty($this->issues)) {
            $this->info('✅ No hardcoded enum values found!');

            return self::SUCCESS;
        }

        $this->displayResults();

        if ($mode === 'scan') {
            return self::SUCCESS;
        }

        // Preview or apply
        if ($mode === 'fix') {
            $this->info('🔍 Dry-run mode — showing proposed changes:');
            $this->showProposedChanges();

            if (confirm('Would you like to apply these changes?', false)) {
                return $this->applyChanges(withBackup: confirm('Create backup first?', true));
            }

            return self::SUCCESS;
        }

        // Apply mode
        $withBackup = confirm('Create backup before applying changes?', true);

        return $this->applyChanges(withBackup: $withBackup);
    }

    /**
     * Run normalize keys mode.
     */
    private function runNormalizeKeys(): int
    {
        $this->displayBanner();
        $this->line('<fg=yellow;options=bold>🔠 Key Normalization Mode</>');
        $this->newLine();

        $this->loadEnumsWithPaths();

        if (count($this->enums) === 0) {
            $this->components->error('No enums found. Check your enumify.paths.enums configuration.');

            return self::FAILURE;
        }

        // Find non-uppercase keys
        $this->findNonUppercaseKeys();

        if (empty($this->keyNormalizationIssues)) {
            $this->components->info('✅ All enum keys are already UPPERCASE!');

            return self::SUCCESS;
        }

        $this->displayKeyNormalizationResults();

        $isDryRun = $this->option('dry-run');
        $isFix = $this->option('fix');

        if ($isDryRun) {
            $this->newLine();
            $this->warn('Dry-run mode — no changes made. Run with --fix to apply.');

            return self::SUCCESS;
        }

        if ($isFix) {
            $withBackup = (bool) $this->option('backup');

            return $this->applyKeyNormalization(withBackup: $withBackup);
        }

        $this->newLine();
        $this->info('💡 Run with <fg=yellow>--dry-run</> to preview or <fg=yellow>--fix</> to apply changes.');

        return self::SUCCESS;
    }

    /**
     * Run normalize keys in interactive mode.
     *
     * @codeCoverageIgnore
     */
    private function runNormalizeKeysInteractive(): int
    {
        $this->loadEnumsWithPaths();

        if (count($this->enums) === 0) {
            $this->error('No enums found.');

            return self::FAILURE;
        }

        $this->findNonUppercaseKeys();

        if (empty($this->keyNormalizationIssues)) {
            $this->components->info('✅ All enum keys are already UPPERCASE!');

            return self::SUCCESS;
        }

        $this->displayKeyNormalizationResults();

        $apply = confirm('Apply key normalization?', false);

        if ($apply) {
            $withBackup = confirm('Create backup first?', true);

            return $this->applyKeyNormalization(withBackup: $withBackup);
        }

        return self::SUCCESS;
    }

    /**
     * Load all enum classes from configured paths.
     */
    private function loadEnums(): void
    {
        /** @var array<string> $paths */
        $paths = config('enumify.paths.enums', ['app/Enums']);

        $this->info('📦 Loading enums...');

        $targetEnum = $this->option('enum');

        foreach ($paths as $path) {
            // Support both absolute and relative paths
            $fullPath = $this->isAbsolutePath($path) ? $path : base_path($path);

            if (! is_dir($fullPath)) {
                continue;
            }

            $files = File::allFiles($fullPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->getClassFromFile($file->getPathname());

                if (! $className || ! enum_exists($className)) {
                    continue;
                }

                $shortName = class_basename($className);
                if ($targetEnum && $shortName !== $targetEnum) {
                    continue;
                }

                try {
                    $reflection = new ReflectionEnum($className);

                    if (! $reflection->isBacked()) {
                        continue;
                    }

                    $cases = [];
                    foreach ($reflection->getCases() as $case) {
                        $value = $case->getBackingValue();
                        if (is_string($value) || is_int($value)) {
                            $cases[$case->getName()] = (string) $value;
                        }
                    }

                    $this->enums[$className] = [
                        'name' => $reflection->getShortName(),
                        'cases' => $cases,
                        'class' => $className,
                        'path' => $file->getPathname(),
                    ];
                } catch (Exception) {  // @codeCoverageIgnore
                    // Skip enums that can't be reflected
                }
            }
        }

        $count = count($this->enums);
        $this->info("✅ Loaded {$count} enum".($count !== 1 ? 's' : ''));
        $this->newLine();
    }

    private function loadEnumsWithPaths(): void
    {
        $this->loadEnums();
    }

    /**
     * Check if a path is absolute.
     */
    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        // @codeCoverageIgnoreStart
        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get FQCN from a PHP file.
     */
    private function getClassFromFile(string $path): ?string
    {
        $content = file_get_contents($path);

        if (! preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            return null;
        }

        // Use multiline anchor to avoid matching 'enum' in comments
        if (! preg_match('/^enum\s+(\w+)/m', $content, $enumMatch)) {
            return null;
        }

        return $namespaceMatch[1].'\\'.$enumMatch[1];
    }

    /**
     * Find non-uppercase enum keys.
     */
    private function findNonUppercaseKeys(): void
    {
        $this->info('🔍 Scanning for non-UPPERCASE enum keys...');
        $this->newLine();

        foreach ($this->enums as $className => $enumData) {
            foreach ($enumData['cases'] as $caseName => $caseValue) {
                // Check if key is already uppercase
                if ($caseName === mb_strtoupper($caseName)) {
                    continue;
                }

                $newKey = mb_strtoupper($caseName);

                // Find references in codebase
                $references = $this->findEnumReferences($enumData['name'], $caseName);

                $this->keyNormalizationIssues[] = [
                    'enum' => $enumData['name'],
                    'oldKey' => $caseName,
                    'newKey' => $newKey,
                    'file' => $enumData['path'],
                    'class' => $className,
                    'references' => $references,
                ];
            }
        }
    }

    /**
     * Find all references to an enum case in the codebase.
     *
     * @return array<array{file: string, line: int, code: string}>
     */
    private function findEnumReferences(string $enumName, string $caseName): array
    {
        $references = [];
        $searchPattern = $enumName.'::'.$caseName;

        $pathOption = $this->option('path');
        if ($pathOption) {
            $scanPath = $this->isAbsolutePath($pathOption) ? $pathOption : base_path($pathOption);
        } else {
            $scanPath = is_dir(app_path()) ? app_path() : base_path();  // @codeCoverageIgnore
        }

        if (! is_dir($scanPath)) {
            return $references;  // @codeCoverageIgnore
        }

        $files = File::allFiles($scanPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;  // @codeCoverageIgnore
            }

            $content = file_get_contents($file->getPathname());
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $line) {
                if (str_contains($line, $searchPattern)) {
                    $references[] = [
                        'file' => str_replace(base_path().'/', '', $file->getPathname()),
                        'line' => $lineNum + 1,
                        'code' => trim($line),
                    ];
                }
            }
        }

        return $references;
    }

    /**
     * Display key normalization results.
     */
    private function displayKeyNormalizationResults(): void
    {
        $this->warn('⚠️  Found '.count($this->keyNormalizationIssues).' key(s) to normalize:');
        $this->newLine();

        $totalRefs = 0;

        foreach ($this->keyNormalizationIssues as $issue) {
            $refCount = count($issue['references']);
            $totalRefs += $refCount;

            $this->line("<fg=cyan>{$issue['enum']}</>");
            $this->line("  <fg=red>{$issue['oldKey']}</> → <fg=green>{$issue['newKey']}</> <fg=gray>({$refCount} reference".($refCount !== 1 ? 's' : '').')</>');

            if ($this->option('detailed') && ! empty($issue['references'])) {
                foreach ($issue['references'] as $ref) {
                    $this->line("    <fg=gray>L{$ref['line']}</> {$ref['file']}");
                }
            }
        }

        $this->newLine();
        $this->info('📊 Total: '.count($this->keyNormalizationIssues)." keys, {$totalRefs} references");
    }

    /**
     * Apply key normalization changes.
     */
    private function applyKeyNormalization(bool $withBackup): int
    {
        $this->info('✏️  Applying key normalization...');
        $this->newLine();

        $filesChanged = 0;
        $keysChanged = 0;
        $refsUpdated = 0;

        // Group by enum file
        $byFile = [];
        foreach ($this->keyNormalizationIssues as $issue) {
            $byFile[$issue['file']][] = $issue;
        }

        // Update enum files
        foreach ($byFile as $filePath => $issues) {
            $content = file_get_contents($filePath);

            if ($withBackup) {
                $this->createBackup($filePath, $content);
            }

            foreach ($issues as $issue) {
                // Replace case declaration: case OldKey = 'value' -> case NEW_KEY = 'value'
                $pattern = '/case\s+'.preg_quote($issue['oldKey'], '/').'\s*=/';
                $replacement = 'case '.$issue['newKey'].' =';
                $content = preg_replace($pattern, $replacement, $content);

                // Replace self references within the enum file
                $content = str_replace(
                    'self::'.$issue['oldKey'],
                    'self::'.$issue['newKey'],
                    $content
                );

                $keysChanged++;
            }

            file_put_contents($filePath, $content);
            $filesChanged++;

            $relativePath = str_replace(base_path().'/', '', $filePath);
            $this->line("<fg=green>✓</> {$relativePath} <fg=gray>(".count($issues).' keys)</>');
        }

        // Update references throughout the codebase
        $refsByFile = [];
        foreach ($this->keyNormalizationIssues as $issue) {
            foreach ($issue['references'] as $ref) {
                // Handle absolute paths for references
                $fullPath = $this->isAbsolutePath($ref['file']) ? $ref['file'] : base_path($ref['file']);

                $refsByFile[$fullPath][] = [
                    'enum' => $issue['enum'],
                    'oldKey' => $issue['oldKey'],
                    'newKey' => $issue['newKey'],
                ];
            }
        }

        foreach ($refsByFile as $filePath => $refs) {
            if (! file_exists($filePath)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            $content = file_get_contents($filePath);

            if ($withBackup && ! isset($this->backups[$filePath])) {
                $this->createBackup($filePath, $content);
            }

            foreach ($refs as $ref) {
                $search = $ref['enum'].'::'.$ref['oldKey'];
                $replace = $ref['enum'].'::'.$ref['newKey'];
                $content = str_replace($search, $replace, $content);
                $refsUpdated++;
            }

            file_put_contents($filePath, $content);
            $filesChanged++;

            $relativePath = str_replace(base_path().'/', '', $filePath);
            $this->line("<fg=green>✓</> {$relativePath} <fg=gray>(".count($refs).' refs)</>');
        }

        $this->newLine();
        $this->info("✅ Normalized {$keysChanged} keys, updated {$refsUpdated} references in {$filesChanged} file(s)");

        if ($withBackup) {
            $this->line('<fg=gray>Backups saved to: storage/app/enumify-refactor-backups/</>');
        }

        return self::SUCCESS;
    }

    /**
     * Scan for hardcoded enum values.
     */
    private function scan(): int
    {
        $pathOption = $this->option('path');

        if ($pathOption) {
            $path = $this->isAbsolutePath($pathOption) ? $pathOption : base_path($pathOption);
        } else {
            // Default to configured enum paths or base_path if app_path doesn't exist
            $path = is_dir(app_path()) ? app_path() : base_path();  // @codeCoverageIgnore
        }

        if (! is_dir($path)) {
            $this->error("Directory not found: {$pathOption}");

            return self::FAILURE;
        }

        $this->info("🔍 Scanning: {$path}");
        $this->newLine();

        $this->scanDirectory($path);

        if ($this->option('json')) {
            return $this->outputJson();
        }

        $this->displayResults();

        // Export report if requested
        $reportPath = $this->option('report');
        if ($reportPath) {
            $this->exportReport($reportPath);
        }

        return self::SUCCESS;
    }

    /**
     * Scan a directory for hardcoded enum values.
     *
     * @param  array<int, string>|null  $targetEnums
     */
    private function scanDirectory(string $path, ?array $targetEnums = null): void
    {
        $files = File::allFiles($path);
        $configExcludes = config('enumify.refactor.exclude', []);
        $excludes = array_merge($this->defaultExcludes, $configExcludes, $this->option('exclude') ?? []);

        // Filter PHP files
        $phpFiles = array_filter($files, function ($file) use ($excludes) {
            $relativePath = $file->getRelativePathname();

            foreach ($excludes as $exclude) {
                if (str_contains($relativePath, $exclude)) {
                    return false;
                }
            }

            return $file->getExtension() === 'php';
        });

        $phpFiles = array_values($phpFiles);

        if (empty($phpFiles)) {
            $this->warn('No PHP files found to scan.');

            return;
        }

        $this->withProgressBar($phpFiles, function ($file) use ($targetEnums) {
            $this->scanFile($file->getPathname(), $targetEnums);
        });

        $this->newLine();
    }

    /**
     * Scan a single file for hardcoded enum values.
     *
     * @param  array<int, string>|null  $targetEnums
     */
    private function scanFile(string $filePath, ?array $targetEnums = null): void
    {
        $content = file_get_contents($filePath);
        $relativePath = str_replace(base_path().'/', '', $filePath);
        $lines = explode("\n", $content);

        foreach ($this->patterns as $type => $pattern) {
            if ($type === 'validation') {
                $this->scanValidationRules($relativePath, $content, $lines);

                continue;
            }

            preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

            if (empty($matches[0])) {
                continue;
            }

            foreach ($matches[0] as $index => $match) {
                $column = $matches[1][$index][0] ?? '';
                $value = $matches[2][$index][0] ?? '';

                $lineNumber = mb_substr_count(mb_substr($content, 0, $match[1]), "\n") + 1;
                $context = $this->getContext($lines, $lineNumber);

                $this->checkAndAddIssue(
                    file: $relativePath,
                    code: $match[0],
                    column: $column,
                    value: $value,
                    type: $type,
                    line: $lineNumber,
                    context: $context,
                    targetEnums: $targetEnums
                );
            }
        }
    }

    /**
     * Scan for Rule::in validation patterns.
     *
     * @param  array<int, string>  $lines
     */
    private function scanValidationRules(string $file, string $content, array $lines): void
    {
        preg_match_all('/Rule::in\(\[([^\]]+)\]\)/', $content, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match) {
            $values = $match[0];
            $lineNumber = mb_substr_count(mb_substr($content, 0, $match[1]), "\n") + 1;

            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $values, $valueMatches);

            foreach ($valueMatches[1] as $value) {
                $this->checkAndAddIssue(
                    file: $file,
                    code: "Rule::in([...'{$value}'...])",
                    column: 'validation',
                    value: $value,
                    type: 'validation',
                    line: $lineNumber,
                    context: $this->getContext($lines, $lineNumber)
                );
            }
        }
    }

    /**
     * Get surrounding context lines.
     *
     * @param  array<int, string>  $lines
     */
    private function getContext(array $lines, int $lineNumber): string
    {
        $start = max(0, $lineNumber - 2);
        $end = min(count($lines), $lineNumber + 1);

        return implode("\n", array_slice($lines, $start, $end - $start));
    }

    /**
     * Check if a value matches an enum and add to issues.
     *
     * @param  array<int, string>|null  $targetEnums
     */
    private function checkAndAddIssue(
        string $file,
        string $code,
        string $column,
        string $value,
        string $type,
        int $line,
        string $context,
        ?array $targetEnums = null
    ): void {
        $isStrict = $this->option('strict');

        foreach ($this->enums as $enumClass => $enumData) {
            if ($targetEnums && ! in_array($enumData['name'], $targetEnums)) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            foreach ($enumData['cases'] as $caseName => $caseValue) {
                if ($caseValue === $value) {
                    if ($isStrict) {
                        $enumLower = mb_strtolower($enumData['name']);
                        $columnLower = mb_strtolower($column);
                        if (! str_contains($enumLower, $columnLower) && ! str_contains($columnLower, $enumLower)) {
                            continue;
                        }
                    }

                    $this->issues[] = [
                        'file' => $file,
                        'line' => $line,
                        'type' => $type,
                        'column' => $column,
                        'value' => $value,
                        'code' => $code,
                        'enum' => $enumData['name'],
                        'case' => $caseName,
                        'class' => $enumClass,
                        'context' => $context,
                    ];

                    return;
                }
            }
        }
    }

    /**
     * Display scan results.
     */
    private function displayResults(): void
    {
        if (empty($this->issues)) {
            $this->info('✅ No hardcoded enum values found!');

            return;
        }

        $this->warn('⚠️  Found '.count($this->issues).' potential hardcoded enum value(s):');
        $this->newLine();

        $byFile = [];
        foreach ($this->issues as $issue) {
            $byFile[$issue['file']][] = $issue;
        }

        foreach ($byFile as $file => $issues) {
            $this->line("<fg=cyan>{$file}</> <fg=gray>(".count($issues).' issue'.(count($issues) > 1 ? 's' : '').')</>');

            foreach ($issues as $issue) {
                $suggestion = $this->generateSuggestion($issue);
                $this->line("  <fg=gray>L{$issue['line']}</> <fg=yellow>•</> <fg=red>{$issue['code']}</>");
                $this->line("       <fg=green>→</> <fg=white>{$suggestion}</>");

                if ($this->option('detailed')) {
                    $this->line("       <fg=gray>Enum: {$issue['class']}::{$issue['case']}</>");
                }
            }

            $this->newLine();
        }

        $this->displaySummaryTable();
    }

    /**
     * Display summary table.
     */
    private function displaySummaryTable(): void
    {
        $byEnum = [];
        $byType = [];

        foreach ($this->issues as $issue) {
            $byEnum[$issue['enum']] = ($byEnum[$issue['enum']] ?? 0) + 1;
            $byType[$issue['type']] = ($byType[$issue['type']] ?? 0) + 1;
        }

        $this->info('📊 Summary:');
        $this->newLine();

        $enumRows = [];
        foreach ($byEnum as $enum => $count) {
            $enumRows[] = [$enum, $count];
        }
        $this->table(['Enum', 'Issues'], $enumRows);

        $this->newLine();

        $typeRows = [];
        foreach ($byType as $type => $count) {
            $typeRows[] = [ucfirst($type), $count];
        }
        $this->table(['Pattern Type', 'Issues'], $typeRows);

        $this->newLine();
        $this->info('💡 Run with <fg=yellow>--dry-run</> (-d) to preview changes or <fg=yellow>--fix</> (-f) to apply them.');
    }

    /**
     * Generate the suggested replacement code.
     *
     * @param  array<string, mixed>  $issue
     */
    private function generateSuggestion(array $issue): string
    {
        $enum = $issue['enum'];
        $case = $issue['case'];

        return match ($issue['type']) {
            'where' => "->where('{$issue['column']}', {$enum}::{$case})",
            'orWhere' => "->orWhere('{$issue['column']}', {$enum}::{$case})",
            'whereNot' => "->whereNot('{$issue['column']}', {$enum}::{$case})",
            'update' => "->update(['{$issue['column']}' => {$enum}::{$case}])",
            'create' => "->create(['{$issue['column']}' => {$enum}::{$case}])",  // @codeCoverageIgnore
            'array' => "['{$issue['column']}' => {$enum}::{$case}]",
            'comparison' => "\$...->{$issue['column']} === {$enum}::{$case}",
            'validation' => "Rule::enum({$enum}::class)",
            default => "{$enum}::{$case}",
        };
    }

    /**
     * Fix/apply mode.
     */
    private function fix(bool $dryRun): int
    {
        if ($dryRun) {
            $this->info('🔍 <fg=yellow>DRY-RUN MODE</> — No changes will be made');
        } else {
            $this->info('✏️  <fg=green>APPLY MODE</> — Changes will be written to files');
        }
        $this->newLine();

        $path = $this->option('path') ?? app_path();
        if (! is_dir($path)) {
            $path = base_path($this->option('path'));  // @codeCoverageIgnore
        }

        $this->scanDirectory($path);

        if (empty($this->issues)) {
            $this->info('✅ No issues to fix!');

            return self::SUCCESS;
        }

        $this->showProposedChanges();

        if ($dryRun) {
            $this->newLine();
            $this->info('Run with <fg=yellow>--fix</> (-f) to apply these changes.');

            return self::SUCCESS;
        }

        $withBackup = (bool) $this->option('backup');

        return $this->applyChanges(withBackup: $withBackup);
    }

    /**
     * Show proposed changes.
     */
    private function showProposedChanges(): void
    {
        $this->info('📝 Proposed Changes:');
        $this->newLine();

        $byFile = [];
        foreach ($this->issues as $issue) {
            $byFile[$issue['file']][] = $issue;
        }

        foreach ($byFile as $file => $issues) {
            $this->line("<fg=cyan>{$file}</>:");

            foreach ($issues as $issue) {
                $this->line("  <fg=gray>L{$issue['line']}</> <fg=red>- {$issue['code']}</>");
                $this->line("  <fg=gray>L{$issue['line']}</> <fg=green>+ {$this->generateSuggestion($issue)}</>");
            }

            $this->newLine();
        }
    }

    /**
     * Apply changes to files.
     */
    private function applyChanges(bool $withBackup): int
    {
        $byFile = [];
        foreach ($this->issues as $issue) {
            $byFile[$issue['file']][] = $issue;
        }

        $filesChanged = 0;
        $changesApplied = 0;

        foreach ($byFile as $file => $issues) {
            $fullPath = $this->isAbsolutePath($file) ? $file : base_path($file);
            if (! file_exists($fullPath)) {
                // @codeCoverageIgnoreStart
                $this->warn("File not found: {$fullPath}");

                continue;
                // @codeCoverageIgnoreEnd
            }

            $content = file_get_contents($fullPath);

            if ($withBackup) {
                $this->createBackup($fullPath, $content);
            }

            $importsNeeded = [];

            foreach ($issues as $issue) {
                $suggestion = $this->generateSuggestion($issue);
                $pattern = preg_quote($issue['code'], '/');

                if (preg_match("/{$pattern}/", $content)) {
                    $content = preg_replace("/{$pattern}/", $suggestion, $content, 1);
                    $importsNeeded[$issue['class']] = $issue['enum'];
                    $changesApplied++;
                }
            }

            if (! empty($importsNeeded)) {
                $content = $this->addImports($content, $importsNeeded);
            }

            file_put_contents($fullPath, $content);
            $filesChanged++;

            $this->line("<fg=green>✓</> {$file} <fg=gray>(".count($issues).' changes)</>');
        }

        $this->newLine();
        $this->info("✅ Applied {$changesApplied} changes in {$filesChanged} file(s)");

        if ($withBackup) {
            $this->line('<fg=gray>Backups saved to: storage/app/enumify-refactor-backups/</>');
        }

        return self::SUCCESS;
    }

    /**
     * Create a backup of a file.
     */
    private function createBackup(string $fullPath, string $content): void
    {
        $backupDir = storage_path('app/enumify-refactor-backups/'.date('Y-m-d_His'));

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $relativePath = str_replace(base_path().'/', '', $fullPath);
        $backupPath = $backupDir.'/'.str_replace('/', '_', $relativePath);

        file_put_contents($backupPath, $content);
        $this->backups[$fullPath] = $backupPath;
    }

    /**
     * Add import statements to a file.
     *
     * @param  array<string, string>  $imports
     */
    private function addImports(string $content, array $imports): string
    {
        if (! preg_match('/namespace\s+[\w\\\\]+;/', $content, $namespaceMatch, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $namespaceEnd = $namespaceMatch[0][1] + mb_strlen($namespaceMatch[0][0]);
        $afterNamespace = mb_substr($content, $namespaceEnd);

        $existingImports = [];
        if (preg_match_all('/use\s+([\w\\\\]+);/', $afterNamespace, $useMatches)) {
            $existingImports = $useMatches[1];
        }

        $newImports = [];
        foreach ($imports as $class => $shortName) {
            if (! in_array($class, $existingImports)) {
                $newImports[] = "use {$class};";
            }
        }

        if (empty($newImports)) {
            return $content;
        }

        if (preg_match_all('/use\s+[\w\\\\]+;/', $afterNamespace, $useMatches, PREG_OFFSET_CAPTURE)) {
            $lastUse = end($useMatches[0]);
            $insertPos = $namespaceEnd + $lastUse[1] + mb_strlen($lastUse[0]);

            return mb_substr($content, 0, $insertPos)."\n".implode("\n", $newImports).mb_substr($content, $insertPos);
        }

        return mb_substr($content, 0, $namespaceEnd)."\n\n".implode("\n", $newImports).$afterNamespace;
    }

    /**
     * Output results as JSON.
     */
    private function outputJson(): int
    {
        $output = [
            'summary' => [
                'total_issues' => count($this->issues),
                'enums_loaded' => count($this->enums),
                'scanned_at' => now()->toIso8601String(),
            ],
            'by_enum' => [],
            'by_file' => [],
            'issues' => $this->issues,
        ];

        foreach ($this->issues as $issue) {
            $output['by_enum'][$issue['enum']] = ($output['by_enum'][$issue['enum']] ?? 0) + 1;
            $output['by_file'][$issue['file']] = ($output['by_file'][$issue['file']] ?? 0) + 1;
        }

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * Export report to a file.
     */
    private function exportReport(string $path): void
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $content = match ($extension) {
            'json' => json_encode($this->issues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'csv' => $this->generateCsvReport(),
            'md' => $this->generateMarkdownReport(),
            default => json_encode($this->issues, JSON_PRETTY_PRINT),
        };

        file_put_contents($path, $content);
        $this->info("📄 Report exported to: {$path}");
    }

    /**
     * Generate CSV report content.
     */
    private function generateCsvReport(): string
    {
        $lines = ['File,Line,Type,Column,Value,Enum,Case,Suggested Fix'];

        foreach ($this->issues as $issue) {
            $suggestion = str_replace(',', ';', $this->generateSuggestion($issue));
            $lines[] = sprintf(
                '%s,%d,%s,%s,%s,%s,%s,"%s"',
                $issue['file'],
                $issue['line'],
                $issue['type'],
                $issue['column'],
                $issue['value'],
                $issue['enum'],
                $issue['case'],
                $suggestion
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Generate Markdown report content.
     */
    private function generateMarkdownReport(): string
    {
        $lines = [
            '# Enumify Refactor Report',
            '',
            'Generated: '.now()->format('Y-m-d H:i:s'),
            '',
            '## Summary',
            '',
            '- **Total Issues:** '.count($this->issues),
            '- **Enums Scanned:** '.count($this->enums),
            '',
            '## Issues by File',
            '',
        ];

        $byFile = [];
        foreach ($this->issues as $issue) {
            $byFile[$issue['file']][] = $issue;
        }

        foreach ($byFile as $file => $issues) {
            $lines[] = "### `{$file}`";
            $lines[] = '';

            foreach ($issues as $issue) {
                $lines[] = "- **Line {$issue['line']}:** `{$issue['code']}`";
                $lines[] = "  - Suggestion: `{$this->generateSuggestion($issue)}`";
                $lines[] = "  - Enum: `{$issue['class']}::{$issue['case']}`";
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
