<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify;

use DevWizardHQ\Enumify\Commands\InstallCommand;
use DevWizardHQ\Enumify\Commands\SyncCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EnumifyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-enumify')
            ->hasConfigFile()
            ->hasCommands([
                InstallCommand::class,
                SyncCommand::class,
            ]);
    }
}
