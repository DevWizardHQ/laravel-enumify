<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

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
        Artisan::call('enumify:refactor', ['--path' => $this->enumPath]);
        $output = Artisan::output();

        expect($output)->toContain('Loading enums');
        expect($output)->toContain('Loaded');
    });

    it('displays results after scanning', function () {
        Artisan::call('enumify:refactor', ['--path' => $this->enumPath]);
        $output = Artisan::output();

        expect($output)->toContain('Scanning');
    });

    it('supports dry-run mode', function () {
        $exitCode = Artisan::call('enumify:refactor', ['--dry-run' => true, '--path' => $this->enumPath]);

        expect($exitCode)->toBe(0);
    });

    it('supports json output format', function () {
        $exitCode = Artisan::call('enumify:refactor', ['--json' => true, '--path' => $this->enumPath]);

        expect($exitCode)->toBe(0);
    });

    it('can filter by specific enum', function () {
        Artisan::call('enumify:refactor', ['--enum' => 'OrderStatus', '--path' => $this->enumPath]);
        $output = Artisan::output();

        expect($output)->toContain('Loaded 1 enum');
    });

    it('handles empty enum directory gracefully', function () {
        $emptyDir = sys_get_temp_dir().'/enumify-empty-'.uniqid();
        mkdir($emptyDir, 0755, true);

        config()->set('enumify.paths.enums', [$emptyDir]);

        Artisan::call('enumify:refactor', ['--path' => $emptyDir]);
        $output = Artisan::output();

        expect($output)->toContain('No enums found');

        @rmdir($emptyDir);
    });
});

describe('enumify:refactor --normalize-keys', function () {
    it('runs key normalization mode', function () {
        Artisan::call('enumify:refactor', ['--normalize-keys' => true]);
        $output = Artisan::output();

        expect($output)->toContain('Key Normalization Mode');
    });

    it('shows success when all keys are uppercase', function () {
        // The test fixtures have uppercase keys, so this should pass
        Artisan::call('enumify:refactor', ['--normalize-keys' => true]);
        $output = Artisan::output();

        expect($output)->toContain('All enum keys are already UPPERCASE');
    });

    it('supports dry-run in normalize mode', function () {
        $exitCode = Artisan::call('enumify:refactor', ['--normalize-keys' => true, '--dry-run' => true]);

        expect($exitCode)->toBe(0);
    });
});

describe('enumify:refactor reports', function () {
    it('can export json report', function () {
        $reportPath = $this->outputPath.'/report.json';

        $exitCode = Artisan::call('enumify:refactor', ['--report' => $reportPath, '--path' => $this->enumPath]);

        expect($exitCode)->toBe(0);
        expect(file_exists($reportPath))->toBeTrue();
    });

    it('can export csv report', function () {
        $reportPath = $this->outputPath.'/report.csv';

        $exitCode = Artisan::call('enumify:refactor', ['--report' => $reportPath, '--path' => $this->enumPath]);

        expect($exitCode)->toBe(0);
        expect(file_exists($reportPath))->toBeTrue();
    });

    it('can export markdown report', function () {
        $reportPath = $this->outputPath.'/report.md';

        $exitCode = Artisan::call('enumify:refactor', ['--report' => $reportPath, '--path' => $this->enumPath]);

        expect($exitCode)->toBe(0);
        expect(file_exists($reportPath))->toBeTrue();
    });
});
