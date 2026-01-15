<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Services\ManifestManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->outputPath = sys_get_temp_dir().'/enumify-manifest-'.uniqid();
    mkdir($this->outputPath, 0755, true);
    $this->manager = new ManifestManager($this->outputPath);
});

afterEach(function () {
    // Close Mockery to properly restore error handlers after File:: facade mocks
    Mockery::close();

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

describe('ManifestManager', function () {
    it('returns null when manifest is missing', function () {
        expect($this->manager->read())->toBeNull();
    });

    it('returns null when manifest JSON is invalid', function () {
        file_put_contents($this->manager->getManifestPath(), '{invalid');

        expect($this->manager->read())->toBeNull();
    });

    it('writes and reads manifest data', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [],
        );

        $entry = $this->manager->buildEntry($enum, 'status', 'content');
        $this->manager->write([$entry], '1.0.0');

        $manifest = $this->manager->read();

        expect($manifest)
            ->toHaveKey('enums')
            ->and($manifest['enums'][0]['fqcn'])
            ->toBe('App\Enums\Status')
            ->and($manifest['version'])
            ->toBe('1.0.0');
    });

    it('returns true when no matching manifest entry exists', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [],
        );

        $entry = $this->manager->buildEntry($enum, 'status', 'content');
        $this->manager->write([$entry], '1.0.0');

        expect($this->manager->needsRegeneration('App\Enums\Missing', 'hash'))->toBeTrue();
    });

    it('cleans temp files when write fails', function () {
        $path = $this->manager->getManifestPath();
        $tempPath = null;

        File::shouldReceive('put')->withArgs(function (string $file, string $content) use (&$tempPath): bool {
            $tempPath = $file;

            return true;
        });

        File::shouldReceive('move')->andThrow(new RuntimeException('Move failed'));
        File::shouldReceive('exists')->withArgs(function (string $file) use (&$tempPath): bool {
            return $tempPath !== null && $file === $tempPath;
        })->andReturn(true);
        File::shouldReceive('delete')->withArgs(function (string $file) use (&$tempPath): bool {
            return $tempPath !== null && $file === $tempPath;
        })->andReturn(true);

        expect(fn () => $this->manager->write([], 'dev'))
            ->toThrow(RuntimeException::class);
    });

    it('returns dev when composer version is missing or invalid', function () {
        $composerPath = dirname(__DIR__, 2).'/composer.json';

        File::shouldReceive('exists')->with($composerPath)->andReturn(true, false);
        File::shouldReceive('get')->with($composerPath)->andReturn('{invalid');

        expect($this->manager->getVersion())->toBe('dev');
        expect($this->manager->getVersion())->toBe('dev');
    });
});
