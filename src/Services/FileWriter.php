<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Services;

use Illuminate\Support\Facades\File;

/**
 * Handles safe file writing operations.
 */
class FileWriter
{
    private const GITKEEP_FILENAME = '.gitkeep';

    public function __construct(
        private readonly string $outputPath,
    ) {}

    /**
     * Ensure the output directory exists with .gitkeep file.
     */
    public function ensureOutputDirectory(): void
    {
        if (! File::isDirectory($this->outputPath)) {
            File::makeDirectory($this->outputPath, 0755, true);
        }

        $this->ensureGitkeep();
    }

    /**
     * Ensure the .gitkeep file exists.
     */
    public function ensureGitkeep(): void
    {
        $gitkeepPath = $this->outputPath.'/'.self::GITKEEP_FILENAME;

        if (! File::exists($gitkeepPath)) {
            File::put($gitkeepPath, '');
        }
    }

    /**
     * Write content to a file atomically.
     *
     * Returns true if the file was written (content changed), false if skipped.
     */
    public function writeFile(string $filename, string $content, bool $force = false): bool
    {
        $filePath = $this->outputPath.'/'.$filename.'.ts';

        // Check if content is unchanged
        if (! $force && File::exists($filePath)) {
            $existingContent = File::get($filePath);

            if ($existingContent === $content) {
                return false;
            }
        }

        // Atomic write: write to temp file then rename
        $tempPath = $filePath.'.tmp.'.uniqid();

        try {
            File::put($tempPath, $content);
            File::move($tempPath, $filePath);

            return true;
        } catch (\Throwable $e) {
            // Clean up temp file if it exists
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }

            throw $e;
        }
    }

    /**
     * Write the barrel index file.
     */
    public function writeBarrel(string $content, bool $force = false): bool
    {
        return $this->writeFileRaw('index.ts', $content, $force);
    }

    /**
     * Write any file (without adding .ts extension).
     */
    private function writeFileRaw(string $filename, string $content, bool $force = false): bool
    {
        $filePath = $this->outputPath.'/'.$filename;

        if (! $force && File::exists($filePath)) {
            $existingContent = File::get($filePath);

            if ($existingContent === $content) {
                return false;
            }
        }

        $tempPath = $filePath.'.tmp.'.uniqid();

        try {
            File::put($tempPath, $content);
            File::move($tempPath, $filePath);

            return true;
        } catch (\Throwable $e) {
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }

            throw $e;
        }
    }

    /**
     * Get the full path for a generated file.
     */
    public function getFilePath(string $filename): string
    {
        return $this->outputPath.'/'.$filename.'.ts';
    }

    /**
     * Delete generated files that are no longer needed.
     *
     * @param  array<string>  $keepFiles  Filenames to keep (without .ts extension)
     * @return array<string> List of deleted files
     */
    public function cleanOrphanedFiles(array $keepFiles): array
    {
        $deleted = [];
        $keepSet = array_flip(array_map(fn ($f) => $f.'.ts', $keepFiles));

        // Add special files to keep
        $keepSet['index.ts'] = true;
        $keepSet[self::GITKEEP_FILENAME] = true;
        $keepSet['.enumify-manifest.json'] = true;

        foreach (File::files($this->outputPath) as $file) {
            $filename = $file->getFilename();

            if (! isset($keepSet[$filename]) && str_ends_with($filename, '.ts')) {
                File::delete($file->getRealPath());
                $deleted[] = $filename;
            }
        }

        return $deleted;
    }

    /**
     * Get the output directory path.
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
