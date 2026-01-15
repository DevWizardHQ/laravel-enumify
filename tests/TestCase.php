<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests;

use DevWizardHQ\Enumify\EnumifyServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'DevWizardHQ\\Enumify\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EnumifyServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Set up default enumify config for tests
        config()->set('enumify.paths.enums', ['tests/Fixtures']);
        config()->set('enumify.paths.output', 'tests/output');
    }

    /**
     * Get the path to the test output directory.
     */
    protected function getOutputPath(): string
    {
        return __DIR__.'/output';
    }

    /**
     * Clean up the test output directory.
     */
    protected function cleanOutput(): void
    {
        $path = $this->getOutputPath();

        if (is_dir($path)) {
            $files = glob($path.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        $this->cleanOutput();
        if (class_exists(HandleExceptions::class)) {
            HandleExceptions::flushState($this);
        }
        parent::tearDown();
    }
}
