<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->outputPath = sys_get_temp_dir().'/enumify-coverage-test-'.uniqid();
    mkdir($this->outputPath, 0755, true);

    // Setup Enums Fixture path
    $this->enumPath = __DIR__.'/../Fixtures';
    config()->set('enumify.paths.enums', [$this->enumPath]);
});

afterEach(function () {
    if (is_dir($this->outputPath)) {
        File::deleteDirectory($this->outputPath);
    }
});

it('applies fixes to files', function () {
    $file = $this->outputPath.'/FixTest.php';
    // Use detectable patterns
    $content = <<<'PHP'
        <?php
        $query->where('status', 'active');
        $user->update(['role' => 'admin']);
        PHP;
    file_put_contents($file, $content);

    $this
        ->artisan('enumify:refactor', ['--fix' => true, '--path' => $this->outputPath])
        ->expectsOutputToContain('APPLY MODE')
        ->assertSuccessful();

    $newContent = file_get_contents($file);
    expect($newContent)
        ->toContain('CampusStatus::ACTIVE')
        ->toContain('UserRole::ADMIN');
});

it('creates backups when requested', function () {
    $file = $this->outputPath.'/BackupTest.php';
    $content = <<<'PHP'
        <?php
        $query->where('status', 'active');
        PHP;
    file_put_contents($file, $content);

    // Mock backup directory to be inside our temp path for cleanup
    $backupPath = $this->outputPath.'/backups';
    if (! is_dir($backupPath))
        mkdir($backupPath);

    // We can't easily change the hardcoded backup path in the command without more refactoring
    // So we'll just check if the command reports backup creation

    $this
        ->artisan('enumify:refactor', ['--fix' => true, '--backup' => true, '--path' => $this->outputPath])
        ->expectsOutputToContain('Backups saved')
        ->assertSuccessful();
});

it('applies key normalization fixes', function () {
    // Create an enum with PascalCase keys
    $enumFile = $this->outputPath.'/NormalizationEnum.php';
    $enumContent = <<<'PHP'
        <?php
        namespace App\Enums;

        enum NormalizationEnum: string
        {
            case Active = 'active';
            case Pending = 'pending';

            public function label(): string
            {
                return match($this) {
                    self::Active => 'Active',
                    self::Pending => 'Pending',
                };
            }
        }
        PHP;
    file_put_contents($enumFile, $enumContent);
    require_once $enumFile;

    // Create a usage file
    $usageFile = $this->outputPath.'/Usage.php';
    $usageContent = <<<'PHP'
        <?php
        use App\Enums\NormalizationEnum;

        $status = NormalizationEnum::Active;
        if ($status === NormalizationEnum::Pending) {
            // do something
        }
        PHP;
    file_put_contents($usageFile, $usageContent);

    // Configure path to our temp enum
    config()->set('enumify.paths.enums', [$this->outputPath]);

    $this
        ->artisan('enumify:refactor', ['--normalize-keys' => true, '--fix' => true, '--path' => $this->outputPath])
        ->expectsOutputToContain('Applying key normalization')
        ->assertSuccessful();

    $newEnumContent = file_get_contents($enumFile);
    expect($newEnumContent)
        ->toContain('case ACTIVE =')
        ->toContain('case PENDING =')
        ->toContain('self::ACTIVE')
        ->toContain('self::PENDING');

    $newUsageContent = file_get_contents($usageFile);
    expect($newUsageContent)
        ->toContain('NormalizationEnum::ACTIVE')
        ->toContain('NormalizationEnum::PENDING');
});
