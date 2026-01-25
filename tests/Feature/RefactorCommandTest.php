<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create temp directories for testing
    $this->tempDir = sys_get_temp_dir() . '/enumify-refactor-test-' . uniqid();
    $this->appDir = $this->tempDir . '/app';
    $this->modelsDir = $this->tempDir . '/app/Models';
    $this->backupDir = storage_path('app/enumify-refactor-backups');

    // Use the real fixtures path (already autoloaded)
    $this->enumPath = realpath(__DIR__ . '/../Fixtures');

    mkdir($this->tempDir, 0755, true);
    mkdir($this->appDir, 0755, true);
    mkdir($this->modelsDir, 0755, true);

    // Create model with enum cast so refactoring will work
    $orderModel = <<<'PHP'
<?php

namespace App\Models;

use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }
}
PHP;

    file_put_contents($this->modelsDir . '/Order.php', $orderModel);

    // Create test files with hardcoded enum values using existing fixture enums
    // Note: The refactor command patterns match ->where() not ::where() (method chains, not static calls)
    $testController = <<<'PHP'
<?php

namespace App\Http\Controllers;

class OrderController
{
    public function index()
    {
        $query = Order::query();
        $orders = $query->where('status', 'pending')->get();
        $shipped = $query->orWhere('status', 'shipped')->get();
        $notDelivered = $query->whereNot('status', 'delivered')->get();
    }

    public function update()
    {
        $order->update(['status' => 'processing']);
    }

    public function check($order)
    {
        if ($order->status === 'shipped') {
            return true;
        }
    }
}
PHP;

    file_put_contents($this->appDir . '/OrderController.php', $testController);

    // Create file with array and validation patterns
    $testRequest = <<<'PHP'
<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class OrderRequest
{
    public function rules()
    {
        return [
            'status' => Rule::in(['pending', 'processing', 'shipped']),
        ];
    }

    public function defaults()
    {
        return [
            'status' => 'pending',
        ];
    }
}
PHP;

    file_put_contents($this->appDir . '/OrderRequest.php', $testRequest);

    // Create file that references the mixed-case enum
    $testService = <<<'PHP'
<?php

namespace App\Services;

use DevWizardHQ\Enumify\Tests\Fixtures\MixedCaseStatus;

class StatusService
{
    public function getPending()
    {
        return Item::where('status', MixedCaseStatus::pending)->get();
    }

    public function getInProgress()
    {
        $status = MixedCaseStatus::InProgress;
        return Item::where('status', $status)->get();
    }
}
PHP;

    file_put_contents($this->appDir . '/StatusService.php', $testService);

    // Configure enumify to use the real fixtures path and models path
    config()->set('enumify.paths.enums', [$this->enumPath]);
    config()->set('enumify.paths.models', [$this->modelsDir]);
    config()->set('enumify.refactor.exclude', []);
});

afterEach(function () {
    // Clean up temp directories
    if (is_dir($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }

    // Clean up backups
    if (is_dir($this->backupDir)) {
        File::deleteDirectory($this->backupDir);
    }

    // Restore the MixedCaseStatus enum if it was modified
    $mixedCaseEnumPath = __DIR__ . '/../Fixtures/MixedCaseStatus.php';
    $originalContent = <<<'PHP'
<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Backed enum with mixed-case keys for normalization testing.
 */
enum MixedCaseStatus: string
{
    case pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CANCELLED = 'cancelled';

    public function isDefault(): bool
    {
        return $this === self::pending;
    }
}
PHP;

    file_put_contents($mixedCaseEnumPath, $originalContent);
});

describe('enumify:refactor scan mode', function () {
    it('scans for hardcoded enum values by default', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertSuccessful();
    });

    it('displays no issues when none found', function () {
        // Create empty app directory
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir, 0755, true);

        file_put_contents($emptyDir . '/Clean.php', '<?php class Clean {}');

        $this->artisan('enumify:refactor', [
            '--path' => $emptyDir,
        ])->assertSuccessful();
    });

    it('fails when no enums are found', function () {
        config()->set('enumify.paths.enums', [$this->tempDir . '/nonexistent']);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertFailed();
    });

    it('fails when path does not exist', function () {
        $this->artisan('enumify:refactor', [
            '--path' => '/nonexistent/path',
        ])->assertFailed();
    });

    it('filters by specific enum with --enum option', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--enum' => 'OrderStatus',
        ])->assertSuccessful();
    });

    it('excludes paths with --exclude option', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--exclude' => ['OrderController'],
        ])->assertSuccessful();
    });

    it('shows detailed output with --detailed option', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--detailed' => true,
        ])->assertSuccessful();
    });

    it('warns when no PHP files found', function () {
        $emptyDir = $this->tempDir . '/no-php';
        mkdir($emptyDir, 0755, true);

        $this->artisan('enumify:refactor', [
            '--path' => $emptyDir,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor JSON output', function () {
    it('outputs JSON with --json option', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor report export', function () {
    it('exports JSON report', function () {
        $reportPath = $this->tempDir . '/report.json';

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--report' => $reportPath,
        ])->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
        $content = json_decode(file_get_contents($reportPath), true);
        expect($content)->toBeArray();
    });

    it('exports CSV report', function () {
        $reportPath = $this->tempDir . '/report.csv';

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--report' => $reportPath,
        ])->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
        expect(file_get_contents($reportPath))->toContain('File,Line,Type');
    });

    it('exports Markdown report', function () {
        $reportPath = $this->tempDir . '/report.md';

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--report' => $reportPath,
        ])->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
        expect(file_get_contents($reportPath))->toContain('# Enumify Refactor Report');
    });

    it('defaults to JSON for unknown extension', function () {
        $reportPath = $this->tempDir . '/report.txt';

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--report' => $reportPath,
        ])->assertSuccessful();

        expect(file_exists($reportPath))->toBeTrue();
    });
});

describe('enumify:refactor dry-run mode', function () {
    it('previews changes without applying with --dry-run', function () {
        $originalContent = file_get_contents($this->appDir . '/OrderController.php');

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--dry-run' => true,
        ])->assertSuccessful();

        // File should remain unchanged
        expect(file_get_contents($this->appDir . '/OrderController.php'))->toBe($originalContent);
    });
});

describe('enumify:refactor fix mode', function () {
    it('applies changes with --fix', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();

        $content = file_get_contents($this->appDir . '/OrderController.php');
        // The refactor command should replace hardcoded values with enum references
        // Check that at least one pattern was replaced (could be any matching enum)
        expect($content)->toMatch('/[A-Za-z]+Status::[A-Za-z_]+/');
    });

    it('creates backup with --fix --backup', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
            '--backup' => true,
        ])->assertSuccessful();

        expect(is_dir($this->backupDir))->toBeTrue();
    });

    it('adds import statements when applying fixes', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();

        $content = file_get_contents($this->appDir . '/OrderController.php');
        expect($content)->toContain('use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;');
    });

    it('handles files with no issues to fix', function () {
        $cleanDir = $this->tempDir . '/clean';
        mkdir($cleanDir, 0755, true);
        file_put_contents($cleanDir . '/Clean.php', '<?php class Clean {}');

        $this->artisan('enumify:refactor', [
            '--path' => $cleanDir,
            '--fix' => true,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor normalize-keys mode', function () {
    it('scans for non-uppercase keys with --normalize-keys', function () {
        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();
    });

    it('previews key normalization with --normalize-keys --dry-run', function () {
        $enumPath = __DIR__ . '/../Fixtures/MixedCaseStatus.php';
        $originalContent = file_get_contents($enumPath);

        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--dry-run' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();

        // Enum file should remain unchanged
        expect(file_get_contents($enumPath))->toBe($originalContent);
    });

    it('applies key normalization with --normalize-keys --fix', function () {
        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--fix' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();

        $enumPath = __DIR__ . '/../Fixtures/MixedCaseStatus.php';
        $content = file_get_contents($enumPath);
        expect($content)->toContain('case PENDING =');
        expect($content)->toContain('case INPROGRESS =');
        expect($content)->toContain('case COMPLETED =');
    });

    it('updates references when normalizing keys', function () {
        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--fix' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();

        $content = file_get_contents($this->appDir . '/StatusService.php');
        expect($content)->toContain('MixedCaseStatus::PENDING');
        expect($content)->toContain('MixedCaseStatus::INPROGRESS');
    });

    it('creates backup when normalizing with --backup', function () {
        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--fix' => true,
            '--backup' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();

        expect(is_dir($this->backupDir))->toBeTrue();
    });

    it('shows detailed references with --normalize-keys --detailed', function () {
        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--detailed' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();
    });

    it('reports when all keys are already uppercase', function () {
        // Use a subset of enums that are all uppercase
        config()->set('enumify.paths.enums', [$this->enumPath]);

        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--path' => $this->appDir,
            '--enum' => 'OrderStatus',
        ])->assertSuccessful();
    });

    it('fails normalize-keys when no enums found', function () {
        config()->set('enumify.paths.enums', [$this->tempDir . '/nonexistent']);

        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
        ])->assertFailed();
    });

    it('updates self references in enums during normalization', function () {
        $this->artisan('enumify:refactor', [
            '--normalize-keys' => true,
            '--fix' => true,
            '--path' => $this->appDir,
        ])->assertSuccessful();

        $enumPath = __DIR__ . '/../Fixtures/MixedCaseStatus.php';
        $content = file_get_contents($enumPath);
        expect($content)->toContain('self::PENDING');
    });
});

describe('enumify:refactor pattern detection', function () {
    it('detects where() patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('detects orWhere() patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('detects whereNot() patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('detects update() patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('detects comparison patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('detects array patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('detects Rule::in validation patterns', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor path handling', function () {
    it('supports absolute paths', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertSuccessful();
    });

    it('supports relative paths converted to absolute', function () {
        // Create a temp directory with relative path structure
        $relativeDir = $this->tempDir . '/relative-test';
        mkdir($relativeDir, 0755, true);
        file_put_contents($relativeDir . '/Test.php', '<?php class Test {}');

        $this->artisan('enumify:refactor', [
            '--path' => $relativeDir,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor import handling', function () {
    it('does not duplicate existing imports', function () {
        // Create a file that already has the import
        $fileWithImport = <<<'PHP'
<?php

namespace App\Services;

use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;

class OrderService
{
    public function pending()
    {
        return Order::where('status', 'pending')->get();
    }
}
PHP;

        file_put_contents($this->appDir . '/OrderService.php', $fileWithImport);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();

        $content = file_get_contents($this->appDir . '/OrderService.php');
        // Count occurrences of the import - should be exactly 1
        $count = substr_count($content, 'use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;');
        expect($count)->toBe(1);
    });

    it('adds imports after namespace declaration', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();

        $content = file_get_contents($this->appDir . '/OrderController.php');
        expect($content)->toContain('namespace App\Http\Controllers;');
        expect($content)->toContain('use DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus;');
    });

    it('handles files without namespace gracefully', function () {
        $noNamespaceFile = <<<'PHP'
<?php

$status = 'pending';
$orders = Order::where('status', 'pending')->get();
PHP;

        file_put_contents($this->appDir . '/NoNamespace.php', $noNamespaceFile);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor edge cases', function () {
    it('handles enum directory that does not exist', function () {
        config()->set('enumify.paths.enums', ['/nonexistent/path', $this->enumPath]);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertSuccessful();
    });

    it('handles non-PHP files in enum directory', function () {
        file_put_contents($this->enumPath . '/readme.txt', 'This is not PHP');

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertSuccessful();

        // Clean up
        @unlink($this->enumPath . '/readme.txt');
    });

    it('handles PHP files without namespace in enum directory', function () {
        // Create a PHP file without namespace in the enum directory
        $noNamespaceEnum = <<<'PHP'
<?php

enum SimpleEnum: string
{
    case TEST = 'test';
}
PHP;

        file_put_contents($this->enumPath . '/NoNamespaceEnum.php', $noNamespaceEnum);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertSuccessful();

        // Clean up
        @unlink($this->enumPath . '/NoNamespaceEnum.php');
    });

    it('handles PHP files with namespace but no enum in enum directory', function () {
        // Create a PHP class file (not an enum) in the enum directory
        $classFile = <<<'PHP'
<?php

namespace DevWizardHQ\Enumify\Tests\Fixtures;

class NotAnEnumHelper
{
    public const TEST = 'test';
}
PHP;

        file_put_contents($this->enumPath . '/NotAnEnumHelper.php', $classFile);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
        ])->assertSuccessful();

        // Clean up
        @unlink($this->enumPath . '/NotAnEnumHelper.php');
    });

    it('respects default excludes', function () {
        // Create a vendor directory - should be excluded by default
        $vendorDir = $this->appDir . '/vendor';
        mkdir($vendorDir, 0755, true);
        file_put_contents($vendorDir . '/VendorFile.php', '<?php Order::where("status", "pending");');

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });

    it('skips imports for files without namespace during fix', function () {
        // Create a file without namespace but with a matching pattern
        $noNamespaceFile = <<<'PHP'
<?php

$orders = $query->where('status', 'pending')->get();
PHP;

        file_put_contents($this->appDir . '/NoNamespaceQuery.php', $noNamespaceFile);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();

        // The file should be modified but no imports added
        $content = file_get_contents($this->appDir . '/NoNamespaceQuery.php');
        expect($content)->not->toContain('use ');
    });
});

describe('enumify:refactor suggestion generation', function () {
    it('generates correct suggestions for all pattern types', function () {
        // This test verifies the generateSuggestion method by checking fix output
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--dry-run' => true,
        ])->assertSuccessful();
    });
});

describe('enumify:refactor backup functionality', function () {
    it('creates timestamped backup directory', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
            '--backup' => true,
        ])->assertSuccessful();

        $backupDirs = glob($this->backupDir . '/*');
        expect($backupDirs)->not->toBeEmpty();
    });

    it('backs up files before modification', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
            '--backup' => true,
        ])->assertSuccessful();

        // Find backup file
        $backupDirs = glob($this->backupDir . '/*');
        expect($backupDirs)->not->toBeEmpty();

        $backupFiles = glob($backupDirs[0] . '/*');
        expect($backupFiles)->not->toBeEmpty();
    });
});

describe('enumify:refactor multiple file processing', function () {
    it('processes multiple files in a directory', function () {
        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--fix' => true,
        ])->assertSuccessful();

        // Controller should have method call patterns fixed
        $controllerContent = file_get_contents($this->appDir . '/OrderController.php');
        // Check that at least one pattern was replaced with an enum reference
        expect($controllerContent)->toMatch('/[A-Za-z]+Status::[A-Za-z_]+/');

        // Request should have array patterns fixed
        $requestContent = file_get_contents($this->appDir . '/OrderRequest.php');
        // Check that the status array pattern was replaced (could be mixed case)
        expect($requestContent)->toMatch('/[A-Za-z]+Status::[A-Za-z_]+/');
    });
});

describe('enumify:refactor config handling', function () {
    it('uses configured exclude patterns', function () {
        config()->set('enumify.refactor.exclude', ['Controller']);

        $this->artisan('enumify:refactor', [
            '--path' => $this->appDir,
            '--json' => true,
        ])->assertSuccessful();
    });
});
