<?php

declare(strict_types=1);

beforeEach(function () {
    $this->outputPath = sys_get_temp_dir().'/enumify-refactor-test-'.uniqid();
    mkdir($this->outputPath, 0755, true);

    // Use direct path without realpath() to avoid issues in some environments
    $this->enumPath = __DIR__.'/../Fixtures';

    config()->set('enumify.paths.enums', [$this->enumPath]);
    config()->set('enumify.paths.output', $this->outputPath);
});

afterEach(function () {
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

describe('enumify:refactor command', function () {
    it('runs and loads enums from configured paths', function () {
        $this
            ->artisan('enumify:refactor', ['--path' => $this->enumPath])
            ->expectsOutputToContain('Loading enums')
            ->expectsOutputToContain('Loaded')
            ->assertSuccessful();
    });

    it('displays results after scanning', function () {
        $this
            ->artisan('enumify:refactor', ['--path' => $this->enumPath])
            ->expectsOutputToContain('Scanning')
            ->assertSuccessful();
    });

    it('supports dry-run mode', function () {
        $this
            ->artisan('enumify:refactor', ['--dry-run' => true, '--path' => $this->enumPath])
            ->assertSuccessful();
    });

    it('supports json output format', function () {
        $this
            ->artisan('enumify:refactor', ['--json' => true, '--path' => $this->enumPath])
            ->assertSuccessful();
    });

    it('can filter by specific enum', function () {
        $this
            ->artisan('enumify:refactor', ['--enum' => 'OrderStatus', '--path' => $this->enumPath])
            ->expectsOutputToContain('Loaded 1 enum')
            ->assertSuccessful();
    });

    it('handles empty enum directory gracefully', function () {
        $emptyDir = sys_get_temp_dir().'/enumify-empty-'.uniqid();
        mkdir($emptyDir, 0755, true);

        config()->set('enumify.paths.enums', [$emptyDir]);

        $this
            ->artisan('enumify:refactor', ['--path' => $emptyDir])
            ->expectsOutputToContain('No enums found')
            ->assertExitCode(1);

        @rmdir($emptyDir);
    });
});

describe('enumify:refactor --normalize-keys', function () {
    it('runs key normalization mode', function () {
        $this
            ->artisan('enumify:refactor', ['--normalize-keys' => true])
            ->expectsOutputToContain('Key Normalization Mode')
            ->assertSuccessful();
    });

    it('shows success when all keys are uppercase', function () {
        // The test fixtures have uppercase keys, so this should pass
        $this
            ->artisan('enumify:refactor', ['--normalize-keys' => true])
            ->expectsOutputToContain('All enum keys are already UPPERCASE')
            ->assertSuccessful();
    });

    it('supports dry-run in normalize mode', function () {
        $this
            ->artisan('enumify:refactor', ['--normalize-keys' => true, '--dry-run' => true])
            ->assertSuccessful();
    });
});

describe('enumify:refactor reports', function () {
    it('can export json report', function () {
        $reportPath = $this->outputPath.'/report.json';

        $this
            ->artisan('enumify:refactor', ['--report' => $reportPath, '--path' => $this->enumPath])
            ->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
    });

    it('can export csv report', function () {
        $reportPath = $this->outputPath.'/report.csv';

        $this
            ->artisan('enumify:refactor', ['--report' => $reportPath, '--path' => $this->enumPath])
            ->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
    });

    it('can export markdown report', function () {
        $reportPath = $this->outputPath.'/report.md';

        $this
            ->artisan('enumify:refactor', ['--report' => $reportPath, '--path' => $this->enumPath])
            ->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
    });
});
