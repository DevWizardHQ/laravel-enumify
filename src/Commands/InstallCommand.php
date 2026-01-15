<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

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

        // Step 3: Show .gitignore instructions
        $this->showGitignoreInstructions();

        // Step 4: Publish config
        $this->publishConfig();

        // Step 5: Show next steps
        $this->showNextSteps();

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

    private function showGitignoreInstructions(): void
    {
        /** @var string $outputPath */
        $outputPath = config('enumify.paths.output', 'resources/js/enums');

        $this->newLine();
        $this->components->warn('Add the following lines to your .gitignore file:');
        $this->newLine();

        $this->line("  <fg=cyan>/{$outputPath}/*</>");
        $this->line("  <fg=cyan>!/{$outputPath}/.gitkeep</>");

        $this->newLine();

        // Try to auto-append to .gitignore
        $gitignorePath = base_path('.gitignore');

        if (File::exists($gitignorePath)) {
            $content = File::get($gitignorePath);
            $pattern1 = "/{$outputPath}/*";
            $pattern2 = "!/{$outputPath}/.gitkeep";

            $hasPattern1 = str_contains($content, $pattern1);
            $hasPattern2 = str_contains($content, $pattern2);

            if ($hasPattern1 && $hasPattern2) {
                $this->components->twoColumnDetail(
                    '.gitignore patterns',
                    '<fg=green>Already configured</>'
                );

                return;
            }

            if (confirm('Would you like to automatically add these patterns to .gitignore?', true)) {
                $additions = [];

                if (! $hasPattern1) {
                    $additions[] = $pattern1;
                }

                if (! $hasPattern2) {
                    $additions[] = $pattern2;
                }

                if (! empty($additions)) {
                    $newContent = rtrim($content)."\n\n# Laravel Enumify\n".implode("\n", $additions)."\n";
                    File::put($gitignorePath, $newContent);

                    $this->components->twoColumnDetail(
                        '.gitignore patterns',
                        '<fg=green>Added</>'
                    );
                }
            }
        }
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

    private function showNextSteps(): void
    {
        $this->newLine();
        $this->components->info('Next steps:');
        $this->newLine();

        $this->line('  1. Install the Vite plugin:');
        $this->newLine();
        $this->line('     <fg=cyan>npm install @devwizard/vite-plugin-enumify --save-dev</>');
        $this->newLine();

        $this->line('  2. Add to your vite.config.js:');
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

        $this->line('  3. Create PHP enums in <fg=cyan>app/Enums/</>');
        $this->newLine();

        $this->line('  4. Run sync manually or start Vite dev server:');
        $this->newLine();
        $this->line('     <fg=cyan>php artisan enumify:sync</>');
        $this->line('     <fg=cyan>npm run dev</>');
        $this->newLine();

        $this->components->info('Done! Run `php artisan enumify:sync` to generate your TypeScript enums.');
    }
}
