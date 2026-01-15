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
});
