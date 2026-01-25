<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->outputPath = sys_get_temp_dir().'/enumify-coverage-test-'.uniqid();
    mkdir($this->outputPath, 0755, true);

    $this->enumPath = __DIR__.'/../Fixtures';
    config()->set('enumify.paths.enums', [$this->enumPath]);
    config()->set('enumify.paths.output', $this->outputPath);
});

afterEach(function () {
    if (is_dir($this->outputPath)) {
        File::deleteDirectory($this->outputPath);
    }
});

describe('enumify:refactor additional coverage tests', function () {
    it('handles validation rules detection', function () {
        $file = $this->outputPath.'/validation_test.php';
        $content = <<<'PHP'
            <?php
            use Illuminate\Validation\Rule;
            $rules = [
                'status' => [Rule::in(['pending', 'completed'])],
            ];
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain("Rule::in([...'pending'...])")
            ->assertSuccessful();
    });

    it('handles backup creation in fix mode', function () {
        $file = $this->outputPath.'/backup_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--fix' => true, '--backup' => true])
            ->expectsOutputToContain('Backups saved to')
            ->assertSuccessful();

        $this->assertTrue(is_dir(storage_path('app/enumify-refactor-backups')));
    });

    it('handles detailed output', function () {
        $file = $this->outputPath.'/detailed_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'active');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--detailed' => true])
            ->expectsOutputToContain('Enum: DevWizardHQ\Enumify\Tests\Fixtures\CampusStatus::ACTIVE')
            ->assertSuccessful();
    });

    it('fails when path does not exist', function () {
        $this
            ->artisan('enumify:refactor', ['--path' => '/non/existent/path'])
            ->expectsOutputToContain('Directory not found')
            ->assertFailed();
    });

    it('handles import addition logic', function () {
        $file = $this->outputPath.'/import_test.php';
        $content = <<<'PHP'
            <?php

            namespace App\Test;

            class TestClass {
                public function query() {
                    return $this->where('status', 'pending');
                }
            }
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--fix' => true])
            ->assertSuccessful();

        $newContent = file_get_contents($file);
        expect($newContent)->toContain('use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;');
        expect($newContent)->toContain('OrderStatus::PENDING');
    });
});

describe('pattern type coverage', function () {
    it('detects orWhere pattern with enum value', function () {
        $file = $this->outputPath.'/or_where_test.php';
        // Use 'pending' which matches OrderStatus::PENDING
        $content = <<<'PHP'
            <?php
            $query->orWhere('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('pending')
            ->assertSuccessful();
    });

    it('detects whereNot pattern with enum value', function () {
        $file = $this->outputPath.'/where_not_test.php';
        // Use 'active' which matches CampusStatus::ACTIVE
        $content = <<<'PHP'
            <?php
            $query->whereNot('status', 'active');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('active')
            ->assertSuccessful();
    });

    it('detects create pattern with enum value', function () {
        $file = $this->outputPath.'/create_test.php';
        // Use 'pending' which matches OrderStatus::PENDING
        $content = <<<'PHP'
            <?php
            Model::create(['status' => 'pending']);
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('pending')
            ->assertSuccessful();
    });

    it('detects comparison pattern with enum value', function () {
        $file = $this->outputPath.'/comparison_test.php';
        // Use 'pending' which matches OrderStatus::PENDING
        $content = <<<'PHP'
            <?php
            if ($model->status === 'pending') {
                // do something
            }
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('pending')
            ->assertSuccessful();
    });

    it('detects array pattern with role value', function () {
        $file = $this->outputPath.'/array_test.php';
        // Use 'admin' which matches UserRole::ADMIN
        $content = <<<'PHP'
            <?php
            $data = [
                'role' => 'admin',
            ];
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('admin')
            ->assertSuccessful();
    });
});

describe('display and report methods', function () {
    it('displays results with multiple issues and shows summary', function () {
        $file = $this->outputPath.'/multi_issue_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            $query->where('status', 'active');
            $query->where('role', 'admin');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('pending')
            ->expectsOutputToContain('active')
            ->expectsOutputToContain('Summary')
            ->assertSuccessful();
    });

    it('generates CSV report with issues', function () {
        $file = $this->outputPath.'/csv_report_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $reportPath = $this->outputPath.'/report.csv';

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--report' => $reportPath])
            ->expectsOutputToContain('Report exported')
            ->assertSuccessful();

        $csvContent = file_get_contents($reportPath);
        expect($csvContent)->toContain('File,Line,Type,Column,Value,Enum,Case');
        expect($csvContent)->toContain('pending');
    });

    it('generates Markdown report with issues', function () {
        $file = $this->outputPath.'/md_report_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $reportPath = $this->outputPath.'/report.md';

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--report' => $reportPath])
            ->expectsOutputToContain('Report exported')
            ->assertSuccessful();

        $mdContent = file_get_contents($reportPath);
        expect($mdContent)->toContain('# Enumify Refactor Report');
        expect($mdContent)->toContain('## Summary');
        expect($mdContent)->toContain('## Issues by File');
    });

    it('generates JSON report with default extension', function () {
        $file = $this->outputPath.'/default_report_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $reportPath = $this->outputPath.'/report.txt';

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--report' => $reportPath])
            ->assertSuccessful();

        $content = file_get_contents($reportPath);
        expect(json_decode($content, true))->toBeArray();
    });

    it('outputs JSON successfully', function () {
        $file = $this->outputPath.'/json_agg_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            $query->where('status', 'active');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--json' => true])
            ->assertSuccessful();
    });
});

describe('fix mode edge cases', function () {
    it('handles dry-run mode with info message', function () {
        $file = $this->outputPath.'/dry_run_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN MODE')
            ->assertSuccessful();

        // Verify file was not modified
        $newContent = file_get_contents($file);
        expect($newContent)->toContain("'pending'");
    });

    it('shows proposed changes in dry-run mode', function () {
        $file = $this->outputPath.'/proposed_changes_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--dry-run' => true])
            ->expectsOutputToContain('Proposed Changes')
            ->assertSuccessful();
    });

    it('applies changes in fix mode', function () {
        $file = $this->outputPath.'/relative_path_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--fix' => true])
            ->expectsOutputToContain('APPLY MODE')
            ->assertSuccessful();
    });
});

describe('import handling edge cases', function () {
    it('adds imports after existing use statements', function () {
        $file = $this->outputPath.'/existing_imports_test.php';
        $content = <<<'PHP'
            <?php

            namespace App\Test;

            use Illuminate\Database\Eloquent\Model;
            use App\SomeClass;

            class TestClass {
                public function query() {
                    return $this->where('status', 'pending');
                }
            }
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--fix' => true])
            ->assertSuccessful();

        $newContent = file_get_contents($file);
        expect($newContent)->toContain('use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;');
        expect($newContent)->toContain('use Illuminate\Database\Eloquent\Model;');
    });

    it('skips files without namespace for import addition', function () {
        $file = $this->outputPath.'/no_namespace_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--fix' => true])
            ->assertSuccessful();

        $newContent = file_get_contents($file);
        // Should replace but not add use statement
        expect($newContent)->toContain('OrderStatus::PENDING');
        expect($newContent)->not->toContain('namespace');
    });

    it('does not duplicate existing imports', function () {
        $file = $this->outputPath.'/duplicate_import_test.php';
        $content = <<<'PHP'
            <?php

            namespace App\Test;

            use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;

            class TestClass {
                public function query() {
                    return $this->where('status', 'pending');
                }
            }
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--fix' => true])
            ->assertSuccessful();

        $newContent = file_get_contents($file);
        // Count occurrences of the import
        $count = substr_count($newContent, 'use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;');
        expect($count)->toBe(1);
    });
});

describe('key normalization edge cases', function () {
    it('shows dry-run message in normalize-keys mode', function () {
        $this
            ->artisan('enumify:refactor', ['--normalize-keys' => true, '--dry-run' => true])
            ->expectsOutputToContain('Key Normalization Mode')
            ->assertSuccessful();
    });

    it('shows info when no changes needed in normalize-keys mode', function () {
        $this
            ->artisan('enumify:refactor', ['--normalize-keys' => true])
            ->expectsOutputToContain('All enum keys are already UPPERCASE')
            ->assertSuccessful();
    });

    it('fails normalize-keys when no enums found', function () {
        $emptyDir = $this->outputPath.'/no_enums';
        mkdir($emptyDir, 0755, true);

        config()->set('enumify.paths.enums', [$emptyDir]);

        $this
            ->artisan('enumify:refactor', ['--normalize-keys' => true])
            ->expectsOutputToContain('No enums found')
            ->assertFailed();
    });

    it('runs dry-run with normalize-keys when issues exist', function () {
        $enumFile = $this->outputPath.'/DryRunEnum.php';
        $enumContent = <<<'PHP'
            <?php
            namespace App\Enums;

            enum DryRunEnum: string
            {
                case PascalCase = 'value';
            }
            PHP;
        file_put_contents($enumFile, $enumContent);
        require_once $enumFile;

        config()->set('enumify.paths.enums', [$this->outputPath]);

        // Test passes if command runs without error
        $this
            ->artisan('enumify:refactor', [
                '--normalize-keys' => true,
                '--dry-run' => true,
                '--path' => $this->outputPath,
            ])
            ->assertSuccessful();
    });

    it('runs normalize-keys without flags when issues exist', function () {
        $enumFile = $this->outputPath.'/InfoEnum.php';
        $enumContent = <<<'PHP'
            <?php
            namespace App\Enums;

            enum InfoEnum: string
            {
                case MixedCase = 'value';
            }
            PHP;
        file_put_contents($enumFile, $enumContent);
        require_once $enumFile;

        config()->set('enumify.paths.enums', [$this->outputPath]);

        // Test passes if command runs without error
        $this
            ->artisan('enumify:refactor', [
                '--normalize-keys' => true,
                '--path' => $this->outputPath,
            ])
            ->assertSuccessful();
    });

    it('handles detailed output with references in normalize-keys', function () {
        // Create an enum with non-uppercase keys
        $enumFile = $this->outputPath.'/DetailedEnum.php';
        $enumContent = <<<'PHP'
            <?php
            namespace App\Enums;

            enum DetailedEnum: string
            {
                case Active = 'active';
            }
            PHP;
        file_put_contents($enumFile, $enumContent);
        require_once $enumFile;

        // Create a usage file
        $usageFile = $this->outputPath.'/DetailedUsage.php';
        $usageContent = <<<'PHP'
            <?php
            use App\Enums\DetailedEnum;
            $status = DetailedEnum::Active;
            PHP;
        file_put_contents($usageFile, $usageContent);

        config()->set('enumify.paths.enums', [$this->outputPath]);

        $this
            ->artisan('enumify:refactor', [
                '--normalize-keys' => true,
                '--detailed' => true,
                '--path' => $this->outputPath,
            ])
            ->expectsOutputToContain('Active')
            ->assertSuccessful();
    });

    it('handles backup during key normalization with references', function () {
        $enumFile = $this->outputPath.'/BackupEnum.php';
        $enumContent = <<<'PHP'
            <?php
            namespace App\Enums;

            enum BackupEnum: string
            {
                case Pending = 'pending';
            }
            PHP;
        file_put_contents($enumFile, $enumContent);
        require_once $enumFile;

        $usageFile = $this->outputPath.'/BackupUsage.php';
        $usageContent = <<<'PHP'
            <?php
            use App\Enums\BackupEnum;
            $status = BackupEnum::Pending;
            PHP;
        file_put_contents($usageFile, $usageContent);

        config()->set('enumify.paths.enums', [$this->outputPath]);

        $this
            ->artisan('enumify:refactor', [
                '--normalize-keys' => true,
                '--fix' => true,
                '--backup' => true,
                '--path' => $this->outputPath,
            ])
            ->expectsOutputToContain('Applying key normalization')
            ->assertSuccessful();

        // Verify changes were applied
        $newEnumContent = file_get_contents($enumFile);
        expect($newEnumContent)->toContain('case PENDING =');

        $newUsageContent = file_get_contents($usageFile);
        expect($newUsageContent)->toContain('BackupEnum::PENDING');
    });
});

describe('scan edge cases', function () {
    it('handles exclude patterns', function () {
        // Create a file that should be excluded
        $excludeDir = $this->outputPath.'/excluded';
        mkdir($excludeDir, 0755, true);

        $excludedFile = $excludeDir.'/excluded_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($excludedFile, $content);

        // Create a file that should be scanned
        $includedFile = $this->outputPath.'/included_test.php';
        file_put_contents($includedFile, $content);

        $this
            ->artisan('enumify:refactor', [
                '--path' => $this->outputPath,
                '--exclude' => ['excluded'],
            ])
            ->expectsOutputToContain('pending')
            ->assertSuccessful();
    });

    it('handles config-based excludes', function () {
        config()->set('enumify.refactor.exclude', ['test_excluded']);

        $excludeDir = $this->outputPath.'/test_excluded';
        mkdir($excludeDir, 0755, true);

        $excludedFile = $excludeDir.'/config_excluded_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            PHP;
        file_put_contents($excludedFile, $content);

        $includedFile = $this->outputPath.'/config_included_test.php';
        file_put_contents($includedFile, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->assertSuccessful();
    });

    it('warns when no PHP files found', function () {
        $emptyDir = $this->outputPath.'/empty_scan';
        mkdir($emptyDir, 0755, true);

        // Create a non-PHP file
        file_put_contents($emptyDir.'/test.txt', 'not php');

        $this
            ->artisan('enumify:refactor', ['--path' => $emptyDir])
            ->expectsOutputToContain('No PHP files found')
            ->assertSuccessful();
    });

    it('handles target enum filtering in checkAndAddIssue', function () {
        $file = $this->outputPath.'/target_enum_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            $query->where('status', 'active');
            PHP;
        file_put_contents($file, $content);

        // Filter to only OrderStatus (has pending) - should find pending but not active (CampusStatus)
        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--enum' => 'OrderStatus'])
            ->expectsOutputToContain('pending')
            ->doesntExpectOutputToContain('active')
            ->assertSuccessful();
    });

    it('handles strict mode filtering correctly', function () {
        $file = $this->outputPath.'/strict_test.php';
        // 'wrong_column' doesn't match enum name, so in strict mode this shouldn't match
        $content = <<<'PHP'
            <?php
            $query->where('wrong_column', 'active');
            PHP;
        file_put_contents($file, $content);

        // In strict mode, 'wrong_column' doesn't relate to 'CampusStatus' so no match
        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--strict' => true])
            ->expectsOutputToContain('No hardcoded enum values found')
            ->assertSuccessful();
    });

    it('matches in strict mode when column relates to enum', function () {
        $file = $this->outputPath.'/strict_match_test.php';
        // 'status' column should match CampusStatus enum in strict mode (both contain 'status')
        $content = <<<'PHP'
            <?php
            $query->where('status', 'active');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath, '--strict' => true])
            ->expectsOutputToContain('active')
            ->assertSuccessful();
    });
});

describe('summary table coverage', function () {
    it('handles multiple issues for same enum in summary table', function () {
        $file = $this->outputPath.'/summary_table_test.php';
        $content = <<<'PHP'
            <?php
            $query->where('status', 'pending');
            $query->where('status', 'processing');
            $query->where('status', 'shipped');
            PHP;
        file_put_contents($file, $content);

        $this
            ->artisan('enumify:refactor', ['--path' => $this->outputPath])
            ->expectsOutputToContain('OrderStatus')
            ->expectsOutputToContain('Summary')
            ->assertSuccessful();
    });
});
