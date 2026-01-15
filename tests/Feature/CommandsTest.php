<?php

declare(strict_types=1);

beforeEach(function () {
    // Create a temp output directory for command testing
    $this->outputPath = sys_get_temp_dir().'/enumify-test-'.uniqid();
    mkdir($this->outputPath, 0755, true);

    // Use the real fixtures path (already autoloaded)
    $this->enumPath = realpath(__DIR__.'/../Fixtures');

    // Configure enumify to use absolute paths
    config()->set('enumify.paths.enums', [$this->enumPath]);
    config()->set('enumify.paths.output', $this->outputPath);
});

afterEach(function () {
    // Clean up output
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

describe('enumify:sync command', function () {
    it('generates typescript files for discovered enums', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        expect(file_exists($this->outputPath.'/order-status.ts'))
            ->toBeTrue()
            ->and(file_exists($this->outputPath.'/priority.ts'))
            ->toBeTrue()
            ->and(file_exists($this->outputPath.'/payment-method.ts'))
            ->toBeTrue();
    });

    it('generates barrel index file', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        $indexPath = $this->outputPath.'/index.ts';
        expect(file_exists($indexPath))->toBeTrue();

        $content = file_get_contents($indexPath);
        expect($content)->toContain("export * from './order-status';");
    });

    it('runs in dry-run mode without writing files', function () {
        $this
            ->artisan('enumify:sync', ['--dry-run' => true])
            ->assertSuccessful();

        // Should not have created any .ts files
        $tsFiles = glob($this->outputPath.'/*.ts');
        expect($tsFiles)->toBeEmpty();
    });

    it('creates .gitkeep even during dry-run', function () {
        $gitkeepPath = $this->outputPath.'/.gitkeep';

        $this
            ->artisan('enumify:sync', ['--dry-run' => true])
            ->assertSuccessful();

        expect(file_exists($gitkeepPath))->toBeTrue();
    });

    it('filters by --only option', function () {
        $this->artisan('enumify:sync', [
            '--force' => true,
            '--only' => 'DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus',
        ])->assertSuccessful();

        expect(file_exists($this->outputPath.'/order-status.ts'))->toBeTrue();

        // Check that the index only exports the one enum
        $indexContent = file_get_contents($this->outputPath.'/index.ts');
        expect($indexContent)
            ->toContain("export * from './order-status';")
            ->and($indexContent)
            ->not
            ->toContain("export * from './priority';");
    });

    it('outputs json format when requested', function () {
        $this->artisan('enumify:sync', [
            '--force' => true,
            '--format' => 'json',
        ])->assertSuccessful();
    });

    it('generates files with correct content for backed enum', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        $content = file_get_contents($this->outputPath.'/order-status.ts');

        expect($content)
            ->toContain('export enum OrderStatus {')
            ->toContain('PENDING = "pending",')
            ->toContain('SHIPPED = "shipped",');
    });

    it('generates files with labels', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        $content = file_get_contents($this->outputPath.'/payment-method.ts');

        expect($content)
            ->toContain('export const PaymentMethodLabels: Record<PaymentMethod, string> = {')
            ->toContain('[PaymentMethod.CREDIT_CARD]: "Credit Card",');
    });

    it('generates files with custom method maps', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        $content = file_get_contents($this->outputPath.'/campus-status.ts');

        expect($content)
            ->toContain('export const CampusStatusColors: Record<CampusStatus, string> = {')
            ->toContain('[CampusStatus.ACTIVE]: "green",')
            ->toContain('export const CampusStatusIsActive: Record<CampusStatus, boolean> = {')
            ->toContain('export function isActive(value: CampusStatus): boolean {');
    });

    it('creates .gitkeep file', function () {
        $gitkeepPath = $this->outputPath.'/.gitkeep';

        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        expect(file_exists($gitkeepPath))->toBeTrue();
    });

    it('generates manifest file', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        $manifestPath = $this->outputPath.'/.enumify-manifest.json';
        expect(file_exists($manifestPath))->toBeTrue();

        $manifest = json_decode(file_get_contents($manifestPath), true);
        expect($manifest)
            ->toHaveKey('enums')
            ->toHaveKey('generated_at')
            ->toHaveKey('version');
    });

    it('skips unchanged files in dry-run and normal mode', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        $this
            ->artisan('enumify:sync', ['--format' => 'json'])
            ->assertSuccessful();

        $this
            ->artisan('enumify:sync', ['--dry-run' => true, '--format' => 'json'])
            ->assertSuccessful();
    });

    it('deletes orphaned files', function () {
        $orphanPath = $this->outputPath.'/orphan.ts';
        file_put_contents($orphanPath, 'export const Orphan = true;');

        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        expect(file_exists($orphanPath))->toBeFalse();
    });

    it('handles empty enum discovery gracefully', function () {
        $emptyDir = sys_get_temp_dir().'/enumify-empty-'.uniqid();
        mkdir($emptyDir, 0755, true);

        config()->set('enumify.paths.enums', [$emptyDir]);

        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();
    });

    it('handles empty enum discovery with quiet flag', function () {
        $emptyDir = sys_get_temp_dir().'/enumify-empty-'.uniqid();
        mkdir($emptyDir, 0755, true);

        config()->set('enumify.paths.enums', [$emptyDir]);

        $this
            ->artisan('enumify:sync', ['--force' => true, '--quiet' => true])
            ->assertSuccessful();
    });

    it('suppresses output with quiet flag and non-json format', function () {
        $this
            ->artisan('enumify:sync', ['--force' => true, '--quiet' => true])
            ->assertSuccessful();
    });

    it('supports relative output paths', function () {
        $relativePath = 'tests/output-relative-'.uniqid();
        config()->set('enumify.paths.output', $relativePath);

        $this
            ->artisan('enumify:sync', ['--force' => true])
            ->assertSuccessful();

        expect(is_dir(base_path($relativePath)))->toBeTrue();
    });
});
