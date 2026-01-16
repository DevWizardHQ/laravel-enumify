<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Commands;

use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Services\EnumDiscoveryService;
use DevWizardHQ\Enumify\Services\FileWriter;
use DevWizardHQ\Enumify\Services\ManifestManager;
use DevWizardHQ\Enumify\Services\TypeScriptGenerator;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'enumify:sync
        {--force : Overwrite all files even if unchanged}
        {--dry-run : Show what would be generated without writing files}
        {--only= : Generate only a specific enum (FQCN)}
        {--format=table : Output format (table, json)}
        ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync PHP enums to TypeScript files';

    private EnumDiscoveryService $discovery;

    private TypeScriptGenerator $generator;

    private FileWriter $writer;

    private ManifestManager $manifest;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->initializeServices();

        // Ensure output directory and .gitkeep exist
        $this->writer->ensureOutputDirectory();

        // Discover enums
        $enums = $this->discoverEnums();

        if (empty($enums)) {
            if ($this->option('quiet')) {
                return self::SUCCESS;
            }

            $this->components->warn('No enums found in configured paths.');

            return self::SUCCESS;
        }

        // Generate TypeScript files
        $results = $this->generateFiles($enums);

        // Generate barrel index
        if (config('enumify.features.generate_index_barrel', true)) {
            $results['barrel'] = $this->generateBarrel($enums);
        }

        // Update manifest
        if (! $this->option('dry-run')) {
            $this->updateManifest($results['entries']);

            // Clean orphaned files
            $this->cleanOrphanedFiles($enums);
        }

        // Display results
        $this->displayResults($results);

        return self::SUCCESS;
    }

    private function initializeServices(): void
    {
        /** @var string $outputPath */
        $outputPath = config('enumify.paths.output', 'resources/js/enums');

        // Support both absolute paths (for testing) and relative paths
        $absoluteOutput = $this->isAbsolutePath($outputPath)
            ? $outputPath
            : base_path($outputPath);

        $this->discovery = new EnumDiscoveryService;

        $this->generator = new TypeScriptGenerator(
            generateUnionTypes: config('enumify.features.generate_union_types', true),
            generateLabelMaps: config('enumify.features.generate_label_maps', true),
            generateMethodMaps: config('enumify.features.generate_method_maps', true),
            localizationMode: config('enumify.localization.mode', 'none'),
        );

        $this->writer = new FileWriter($absoluteOutput);
        $this->manifest = new ManifestManager($absoluteOutput);
    }

    /**
     * Discover enums from configured paths.
     *
     * @return array<EnumDefinition>
     */
    private function discoverEnums(): array
    {
        /** @var array<string> $paths */
        $paths = config('enumify.paths.enums', ['app/Enums']);

        /** @var array<string> $include */
        $include = config('enumify.filters.include', []);

        /** @var array<string> $exclude */
        $exclude = config('enumify.filters.exclude', []);

        $enums = $this->discovery->discover($paths, $include, $exclude);

        // Filter by --only option if specified
        $only = $this->option('only');

        if ($only) {
            $enums = array_filter($enums, fn (EnumDefinition $e) => $e->fqcn === $only);
        }

        return array_values($enums);
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
     * Generate TypeScript files for all enums.
     *
     * @param  array<EnumDefinition>  $enums
     * @return array{generated: int, skipped: int, entries: array<array{fqcn: string, file: string, hash: string}>}
     */
    private function generateFiles(array $enums): array
    {
        $generated = 0;
        $skipped = 0;
        $entries = [];

        /** @var string $fileCase */
        $fileCase = config('enumify.naming.file_case', 'kebab');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($enums as $enum) {
            $filename = $enum->getFilename($fileCase);
            $content = $this->generator->generate($enum);
            $hash = $this->manifest->computeHash($content);

            $needsWrite = $force || $this->manifest->needsRegeneration($enum->fqcn, $hash);

            if ($dryRun) {
                if ($needsWrite) {
                    if (! $this->option('quiet')) {
                        $this->components->twoColumnDetail(
                            $enum->fqcn,
                            '<fg=cyan>Would generate</> '.$filename.'.ts'
                        );
                    }
                    $generated++;
                } else {
                    $skipped++;
                }
            } else {
                if ($needsWrite) {
                    $this->writer->writeFile($filename, $content, $force);
                    if (! $this->option('quiet')) {
                        $this->components->twoColumnDetail(
                            $enum->fqcn,
                            '<fg=green>Generated</> '.$filename.'.ts'
                        );
                    }
                    $generated++;
                } else {
                    $skipped++;
                }
            }

            $entries[] = $this->manifest->buildEntry($enum, $filename, $content);
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'entries' => $entries,
        ];
    }

    /**
     * Generate the barrel index file.
     *
     * @param  array<EnumDefinition>  $enums
     */
    private function generateBarrel(array $enums): bool
    {
        /** @var string $fileCase */
        $fileCase = config('enumify.naming.file_case', 'kebab');
        $content = $this->generator->generateBarrel($enums, $fileCase);

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            if (! $this->option('quiet')) {
                $this->components->twoColumnDetail(
                    'index.ts',
                    '<fg=cyan>Would generate</> barrel file'
                );
            }

            return true;
        }

        $written = $this->writer->writeBarrel($content, $force);

        if ($written && ! $this->option('quiet')) {
            $this->components->twoColumnDetail(
                'index.ts',
                '<fg=green>Generated</> barrel file'
            );
        }

        return $written;
    }

    /**
     * Update the manifest file.
     *
     * @param  array<array{fqcn: string, file: string, hash: string}>  $entries
     */
    private function updateManifest(array $entries): void
    {
        $version = $this->manifest->getVersion();
        $this->manifest->write($entries, $version);
    }

    /**
     * Clean orphaned files that are no longer needed.
     *
     * @param  array<EnumDefinition>  $enums
     */
    private function cleanOrphanedFiles(array $enums): void
    {
        /** @var string $fileCase */
        $fileCase = config('enumify.naming.file_case', 'kebab');

        $keepFiles = array_map(fn (EnumDefinition $e) => $e->getFilename($fileCase), $enums);
        $deleted = $this->writer->cleanOrphanedFiles($keepFiles);

        foreach ($deleted as $filename) {
            if (! $this->option('quiet')) {
                $this->components->twoColumnDetail(
                    $filename,
                    '<fg=yellow>Deleted</> (orphaned)'
                );
            }
        }
    }

    /**
     * Display final results summary.
     *
     * @param  array{generated: int, skipped: int, entries?: array<mixed>, barrel?: bool}  $results
     */
    private function displayResults(array $results): void
    {
        $this->newLine();

        $format = $this->option('format');

        if ($this->option('quiet') && $format !== 'json') {
            return;
        }

        if ($format === 'json') {
            $this->line(json_encode([
                'generated' => $results['generated'],
                'skipped' => $results['skipped'],
                'total' => $results['generated'] + $results['skipped'],
                'dry_run' => (bool) $this->option('dry-run'),
            ], JSON_PRETTY_PRINT));

            return;
        }

        $total = $results['generated'] + $results['skipped'];

        $this->components->bulletList([
            "Total enums: <fg=cyan>{$total}</>",
            "Generated: <fg=green>{$results['generated']}</>",
            "Skipped (unchanged): <fg=yellow>{$results['skipped']}</>",
        ]);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->warn('Dry run mode - no files were written.');
        }
    }
}
