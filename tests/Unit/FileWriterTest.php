<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Services\FileWriter;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->outputPath = sys_get_temp_dir().'/enumify-test-'.uniqid();
    mkdir($this->outputPath, 0755, true);
    $this->writer = new FileWriter($this->outputPath);
});

afterEach(function () {
    // Clean up all files including hidden ones
    if (is_dir($this->outputPath)) {
        $files = array_diff(scandir($this->outputPath), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $this->outputPath.'/'.$file;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        @rmdir($this->outputPath);
    }
});

describe('FileWriter', function () {
    it('ensures output directory exists', function () {
        $newPath = $this->outputPath.'/nested/dir';
        $writer = new FileWriter($newPath);

        $writer->ensureOutputDirectory();

        expect(is_dir($newPath))->toBeTrue();

        // Cleanup
        @rmdir($newPath);
        @rmdir(dirname($newPath));
    });

    it('creates .gitkeep file', function () {
        $this->writer->ensureGitkeep();

        $gitkeepPath = $this->outputPath.'/.gitkeep';
        expect(file_exists($gitkeepPath))->toBeTrue();
    });

    it('writes files with .ts extension', function () {
        $this->writer->writeFile('order-status', 'export enum OrderStatus {}');

        $filePath = $this->outputPath.'/order-status.ts';
        expect(file_exists($filePath))
            ->toBeTrue()
            ->and(file_get_contents($filePath))
            ->toBe('export enum OrderStatus {}');
    });

    it('skips unchanged files when not forced', function () {
        $content = 'export enum Test {}';

        // Write first time
        $written1 = $this->writer->writeFile('test', $content);
        expect($written1)->toBeTrue();

        // Write same content again
        $written2 = $this->writer->writeFile('test', $content);
        expect($written2)->toBeFalse();
    });

    it('overwrites when forced', function () {
        $content1 = 'export enum Test { V1 }';
        $content2 = 'export enum Test { V2 }';

        $this->writer->writeFile('test', $content1);
        $written = $this->writer->writeFile('test', $content2, force: true);

        expect($written)
            ->toBeTrue()
            ->and(file_get_contents($this->outputPath.'/test.ts'))
            ->toBe($content2);
    });

    it('writes barrel index file', function () {
        $content = "export * from './order-status';";

        $this->writer->writeBarrel($content);

        expect(file_exists($this->outputPath.'/index.ts'))
            ->toBeTrue()
            ->and(file_get_contents($this->outputPath.'/index.ts'))
            ->toBe($content);
    });

    it('cleans orphaned files', function () {
        // Create some files
        file_put_contents($this->outputPath.'/order-status.ts', 'content');
        file_put_contents($this->outputPath.'/old-enum.ts', 'content');
        file_put_contents($this->outputPath.'/index.ts', 'content');
        file_put_contents($this->outputPath.'/.gitkeep', '');

        // Clean, keeping only order-status
        $deleted = $this->writer->cleanOrphanedFiles(['order-status']);

        expect($deleted)
            ->toContain('old-enum.ts')
            ->and(file_exists($this->outputPath.'/order-status.ts'))
            ->toBeTrue()
            ->and(file_exists($this->outputPath.'/old-enum.ts'))
            ->toBeFalse()
            ->and(file_exists($this->outputPath.'/index.ts'))
            ->toBeTrue()
            ->and(file_exists($this->outputPath.'/.gitkeep'))
            ->toBeTrue();
    });

    it('preserves .gitkeep when cleaning', function () {
        file_put_contents($this->outputPath.'/.gitkeep', '');

        $this->writer->cleanOrphanedFiles([]);

        expect(file_exists($this->outputPath.'/.gitkeep'))->toBeTrue();
    });

    it('returns correct file path', function () {
        $path = $this->writer->getFilePath('order-status');

        expect($path)->toBe($this->outputPath.'/order-status.ts');
    });

    it('returns the output path', function () {
        expect($this->writer->getOutputPath())->toBe($this->outputPath);
    });

    it('cleans temp files when writeFile fails', function () {
        $filePath = $this->outputPath.'/failure.ts';
        $tempPath = null;

        File::shouldReceive('exists')->andReturnUsing(function (string $path) use ($filePath, &$tempPath): bool {
            if ($path === $filePath) {
                return false;
            }

            return $tempPath !== null && $path === $tempPath;
        });

        File::shouldReceive('put')->withArgs(function (string $path, string $content) use (&$tempPath): bool {
            $tempPath = $path;

            return true;
        });

        File::shouldReceive('move')->andThrow(new RuntimeException('Move failed'));
        File::shouldReceive('delete')->withArgs(function (string $path) use (&$tempPath): bool {
            return $path === $tempPath;
        })->andReturn(true);

        expect(fn () => $this->writer->writeFile('failure', 'content'))
            ->toThrow(RuntimeException::class);
    });

    it('cleans temp files when barrel write fails', function () {
        $filePath = $this->outputPath.'/index.ts';
        $tempPath = null;

        File::shouldReceive('exists')->andReturnUsing(function (string $path) use ($filePath, &$tempPath): bool {
            if ($path === $filePath) {
                return false;
            }

            return $tempPath !== null && $path === $tempPath;
        });

        File::shouldReceive('put')->withArgs(function (string $path, string $content) use (&$tempPath): bool {
            $tempPath = $path;

            return true;
        });

        File::shouldReceive('move')->andThrow(new RuntimeException('Move failed'));
        File::shouldReceive('delete')->withArgs(function (string $path) use (&$tempPath): bool {
            return $path === $tempPath;
        })->andReturn(true);

        expect(fn () => $this->writer->writeBarrel('export {}'))
            ->toThrow(RuntimeException::class);
    });
});
