<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->originalBasePath = app()->basePath();
    $this->tempBasePath = sys_get_temp_dir().'/enumify-install-'.uniqid();

    File::makeDirectory($this->tempBasePath, 0755, true);
    File::makeDirectory($this->tempBasePath.'/config', 0755, true);

    app()->setBasePath($this->tempBasePath);

    config()->set('enumify.paths.output', 'resources/js/enums');
});

afterEach(function () {
    app()->setBasePath($this->originalBasePath);

    if (is_dir($this->tempBasePath)) {
        File::deleteDirectory($this->tempBasePath);
    }
});

describe('enumify:install command', function () {
    it('creates output directory, gitkeep, and appends gitignore', function () {
        File::put($this->tempBasePath.'/.gitignore', "# Base\n");

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'no')
            ->assertSuccessful();

        expect(is_dir($this->tempBasePath.'/resources/js/enums'))
            ->toBeTrue()
            ->and(file_exists($this->tempBasePath.'/resources/js/enums/.gitkeep'))
            ->toBeTrue()
            ->and(file_get_contents($this->tempBasePath.'/.gitignore'))
            ->toContain('/resources/js/enums/*')
            ->toContain('!/resources/js/enums/.gitkeep');
    });

    it('skips gitignore update when patterns already exist can run plugin install logic', function () {
        File::put(
            $this->tempBasePath.'/.gitignore',
            "/resources/js/enums/*\n!/resources/js/enums/.gitkeep\n"
        );

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'no')
            ->assertSuccessful();

        expect(file_get_contents($this->tempBasePath.'/.gitignore'))
            ->toContain('/resources/js/enums/*')
            ->toContain('!/resources/js/enums/.gitkeep');
    });

    it('does not overwrite config when declined', function () {
        $configPath = $this->tempBasePath.'/config/enumify.php';
        File::put($configPath, '<?php return ["custom" => true];');
        File::put(
            $this->tempBasePath.'/.gitignore',
            "/resources/js/enums/*\n!/resources/js/enums/.gitkeep\n"
        );

        $this
            ->artisan('enumify:install', ['--force' => true])
            ->expectsConfirmation('Config file already exists. Overwrite?', 'no')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'no')
            ->assertSuccessful();

        expect(file_get_contents($configPath))->toContain('custom');
    });

    it('overwrites config when confirmed', function () {
        $configPath = $this->tempBasePath.'/config/enumify.php';
        File::put($configPath, '<?php return ["custom" => true];');
        File::put(
            $this->tempBasePath.'/.gitignore',
            "/resources/js/enums/*\n!/resources/js/enums/.gitkeep\n"
        );

        $this
            ->artisan('enumify:install', ['--force' => true])
            ->expectsConfirmation('Config file already exists. Overwrite?', 'yes')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'no')
            ->assertSuccessful();
    });

    it('reports existing output and config without force', function () {
        File::makeDirectory($this->tempBasePath.'/resources/js/enums', 0755, true);
        File::put($this->tempBasePath.'/resources/js/enums/.gitkeep', '');
        File::put($this->tempBasePath.'/config/enumify.php', '<?php return [];');
        File::put(
            $this->tempBasePath.'/.gitignore',
            "/resources/js/enums/*\n!/resources/js/enums/.gitkeep\n"
        );

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'no')
            ->assertSuccessful();
    });
    it('skips gitignore update if .gitignore does not exist', function () {
        $gitignorePath = $this->tempBasePath.'/.gitignore';
        if (File::exists($gitignorePath)) {
            File::delete($gitignorePath);
        }

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'no')
            ->assertSuccessful();

        expect(File::exists($gitignorePath))->toBeFalse();
    });

    it('installs dependency using detected package manager (pnpm)', function () {
        File::put($this->tempBasePath.'/.gitignore', "# Base\n");
        File::put($this->tempBasePath.'/pnpm-lock.yaml', '');

        Process::fake([
            'pnpm add -D @devwizard/vite-plugin-enumify' => Process::result(),
        ]);

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using pnpm?', 'yes')
            ->assertSuccessful();

        Process::assertRan('pnpm add -D @devwizard/vite-plugin-enumify');
    });

    it('installs dependency using detected package manager (yarn)', function () {
        File::put($this->tempBasePath.'/.gitignore', "# Base\n");
        File::put($this->tempBasePath.'/yarn.lock', '');

        Process::fake([
            'yarn add -D @devwizard/vite-plugin-enumify' => Process::result(),
        ]);

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using yarn?', 'yes')
            ->assertSuccessful();

        Process::assertRan('yarn add -D @devwizard/vite-plugin-enumify');
    });

    it('installs dependency using detected package manager (bun)', function () {
        File::put($this->tempBasePath.'/.gitignore', "# Base\n");
        File::put($this->tempBasePath.'/bun.lockb', '');

        Process::fake([
            'bun add -d @devwizard/vite-plugin-enumify' => Process::result(),
        ]);

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using bun?', 'yes')
            ->assertSuccessful();

        Process::assertRan('bun add -d @devwizard/vite-plugin-enumify');
    });

    it('handles installation failure', function () {
        File::put($this->tempBasePath.'/.gitignore', "# Base\n");

        Process::fake(function ($process) {
            return Process::result(
                output: 'Installation failed',
                exitCode: 1
            );
        });

        $this
            ->artisan('enumify:install')
            ->expectsConfirmation('Would you like to install the @devwizard/vite-plugin-enumify plugin using npm?', 'yes')
            ->assertSuccessful()
            ->expectsOutputToContain('Installation failed.');

        Process::assertRan(fn ($process) => str_contains($process->command, 'npm install'));
    });
});
