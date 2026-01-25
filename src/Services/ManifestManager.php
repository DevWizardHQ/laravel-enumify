<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Services;

use DevWizardHQ\Enumify\Data\EnumDefinition;
use Illuminate\Support\Facades\File;

/**
 * Manages the .enumify-manifest.json file.
 */
class ManifestManager
{
    private const MANIFEST_FILENAME = '.enumify-manifest.json';

    public function __construct(
        private readonly string $outputPath,
    ) {}

    /**
     * Get the manifest file path.
     */
    public function getManifestPath(): string
    {
        return $this->outputPath.'/'.self::MANIFEST_FILENAME;
    }

    /**
     * Read the current manifest from disk.
     *
     * @return array{enums: array<array{fqcn: string, file: string, hash: string}>, generated_at: string, version: string}|null
     */
    public function read(): ?array
    {
        $path = $this->getManifestPath();

        if (! File::exists($path)) {
            return null;
        }

        try {
            $content = File::get($path);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Write a new manifest to disk.
     *
     * @param  array<array{fqcn: string, file: string, hash: string}>  $enums
     */
    public function write(array $enums, string $version): void
    {
        $manifest = [
            'enums' => $enums,
            'generated_at' => date('c'), // ISO 8601 format
            'version' => $version,
        ];

        $content = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $path = $this->getManifestPath();
        $tempPath = $path.'.tmp.'.uniqid();

        try {
            File::put($tempPath, $content."\n");
            File::move($tempPath, $path);
        } catch (\Throwable $e) {
            if (File::exists($tempPath)) {
                File::delete($tempPath);
            }

            throw $e;
        }
    }

    /**
     * Check if a file needs to be regenerated based on content hash.
     */
    public function needsRegeneration(string $fqcn, string $contentHash): bool
    {
        $manifest = $this->read();

        if ($manifest === null) {
            return true;
        }

        foreach ($manifest['enums'] as $entry) {
            if ($entry['fqcn'] === $fqcn) {
                return $entry['hash'] !== $contentHash;
            }
        }

        return true;
    }

    /**
     * Build a manifest entry for an enum.
     *
     * @return array{fqcn: string, file: string, hash: string}
     */
    public function buildEntry(EnumDefinition $enum, string $filename, string $content): array
    {
        return [
            'fqcn' => $enum->fqcn,
            'file' => $filename.'.ts',
            'hash' => $this->computeHash($content),
        ];
    }

    /**
     * Compute a content hash.
     */
    public function computeHash(string $content): string
    {
        return hash('xxh3', $content);
    }

    /**
     * Get the package version.
     */
    public function getVersion(): string
    {
        $composerPath = dirname(__DIR__, 2).'/composer.json';

        if (File::exists($composerPath)) {
            try {
                $composer = json_decode(File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);

                return $composer['version'] ?? 'dev';
            } catch (\JsonException) {
                return 'dev';
            }
        }

        return 'dev';
    }
}
