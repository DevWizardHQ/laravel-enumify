<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'enumify:install
        {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Enumify and set up the required directory structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        // Step 1: Create output directory
        $this->createOutputDirectory();

        // Step 2: Create .gitkeep file
        $this->createGitkeep();

        // Step 3: Update .gitignore (Auto)
        $this->updateGitignore();

        // Step 4: Publish config
        $this->publishConfig();

        // Step 5: Install Node Dependency
        $installed = $this->installNodeDependency();

        // Step 6: Show next steps
        $this->showNextSteps($installed);

        return self::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->components->info('Installing Laravel Enumify...');
        $this->newLine();
    }

    private function createOutputDirectory(): void
    {
        /** @var string $outputPath */
        $outputPath = config('enumify.paths.output', 'resources/js/enums');
        $absolutePath = base_path($outputPath);

        if (File::isDirectory($absolutePath)) {
            $this->components->twoColumnDetail(
                'Output directory',
                '<fg=yellow>Already exists</>'
            );

            return;
        }

        File::makeDirectory($absolutePath, 0755, true);

        $this->components->twoColumnDetail(
            'Output directory',
            '<fg=green>Created</> '.$outputPath
        );
    }

    private function createGitkeep(): void
    {
        /** @var string $outputPath */
        $outputPath = config('enumify.paths.output', 'resources/js/enums');
        $gitkeepPath = base_path($outputPath.'/.gitkeep');

        if (File::exists($gitkeepPath)) {
            $this->components->twoColumnDetail(
                '.gitkeep file',
                '<fg=yellow>Already exists</>'
            );

            return;
        }

        File::put($gitkeepPath, '');

        $this->components->twoColumnDetail(
            '.gitkeep file',
            '<fg=green>Created</>'
        );
    }

    private function updateGitignore(): void
    {
        /** @var string $outputPath */
        $outputPath = config('enumify.paths.output', 'resources/js/enums');
        $gitignorePath = base_path('.gitignore');

        if (! File::exists($gitignorePath)) {
            return;
        }

        $content = File::get($gitignorePath);
        $pattern1 = "/{$outputPath}/*";
        $pattern2 = "!/{$outputPath}/.gitkeep";

        $hasPattern1 = str_contains($content, $pattern1);
        $hasPattern2 = str_contains($content, $pattern2);

        if ($hasPattern1 && $hasPattern2) {
            $this->components->twoColumnDetail(
                '.gitignore',
                '<fg=yellow>Already configured</>'
            );

            return;
        }

        $additions = [];
        if (! $hasPattern1) {
            $additions[] = $pattern1;
        }
        if (! $hasPattern2) {
            $additions[] = $pattern2;
        }

        $newContent = rtrim($content)."\n\n# Laravel Enumify\n".implode("\n", $additions)."\n";
        File::put($gitignorePath, $newContent);

        $this->components->twoColumnDetail(
            '.gitignore',
            '<fg=green>Updated</>'
        );
    }

    private function publishConfig(): void
    {
        $configPath = config_path('enumify.php');

        if (File::exists($configPath) && ! $this->option('force')) {
            $this->components->twoColumnDetail(
                'Config file',
                '<fg=yellow>Already exists</>'
            );

            return;
        }

        if (File::exists($configPath)) {
            if (! confirm('Config file already exists. Overwrite?', false)) {
                return;
            }
        }

        $this->call('vendor:publish', [
            '--tag' => 'enumify-config',
            '--force' => true,
        ]);
    }

    private function installNodeDependency(): bool
    {
        $pm = $this->detectPackageManager();
        $pluginName = '@devwizard/vite-plugin-enumify';

        $this->newLine();
        // The prompt specifically requested: "ask to user that they need to the npm/pnpm install @devqizard plugin for not if yes"
        // I will interpret "is not yes" as check for installation.
        // Actually, the request says "ask to user... if yes and enter then run the install command... select no then only show the instruction".
        if (! confirm("Would you like to install the {$pluginName} plugin using {$pm}?", true)) {
            return false;
        }

        $command = match ($pm) {
            'yarn' => "yarn add -D {$pluginName}",
            'pnpm' => "pnpm add -D {$pluginName}",
            'bun' => "bun add -d {$pluginName}",
            default => "npm install --save-dev {$pluginName}",
        };

        $this->components->info("Running: {$command}");

        $result = Process::run($command);

        if ($result->successful()) {
            $this->components->info('Plugin installed successfully!');

            return true;
        }

        $this->components->error('Installation failed.');
        $this->line($result->errorOutput());

        return false;
    }

    private function detectPackageManager(): string
    {
        if (File::exists(base_path('pnpm-lock.yaml'))) {
            return 'pnpm';
        }

        if (File::exists(base_path('yarn.lock'))) {
            return 'yarn';
        }

        if (File::exists(base_path('bun.lockb'))) {
            return 'bun';
        }

        return 'npm';
    }

    private function showNextSteps(bool $installed): void
    {
        $this->newLine();
        $this->components->info('Next steps:');
        $this->newLine();

        if (! $installed) {
            $this->line('  1. Install the Vite plugin:');
            $this->newLine();
            $this->line('     <fg=cyan>npm install @devwizard/vite-plugin-enumify --save-dev</>');
            $this->newLine();
        }

        $idx = $installed ? 1 : 2;

        $this->line("  {$idx}. Add to your vite.config.js:");
        $this->newLine();
        $this->line("     <fg=cyan>import enumify from '@devwizard/vite-plugin-enumify'</>");
        $this->newLine();
        $this->line('     <fg=cyan>export default defineConfig({</>');
        $this->line('     <fg=cyan>  plugins: [</>');
        $this->line('     <fg=cyan>    enumify(),</>');
        $this->line('     <fg=cyan>    laravel({ /* ... */ }),</>');
        $this->line('     <fg=cyan>  ],</>');
        $this->line('     <fg=cyan>})</>');
        $this->newLine();

        $idx++;
        $this->line("  {$idx}. Create PHP enums in <fg=cyan>app/Enums/</>");
        $this->newLine();

        $idx++;
        $this->line("  {$idx}. Run sync manually or start Vite dev server:");
        $this->newLine();
        $this->line('     <fg=cyan>php artisan enumify:sync</>');
        $this->line('     <fg=cyan>npm run dev</>');
        $this->newLine();

        $this->components->info('Done! Run `php artisan enumify:sync` to generate your TypeScript enums.');
    }
}
