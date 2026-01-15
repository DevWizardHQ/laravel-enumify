<?php

declare(strict_types=1);

uses(DevWizardHQ\Enumify\Tests\TestCase::class)->in('Feature', 'Unit');

/*
 * |--------------------------------------------------------------------------
 * | Expectations
 * |--------------------------------------------------------------------------
 */

expect()->extend('toBeFilePath', function () {
    return $this->toBeString()->and(file_exists($this->value))->toBeTrue();
});

/*
 * |--------------------------------------------------------------------------
 * | Functions
 * |--------------------------------------------------------------------------
 */

function getFixturePath(string $name = ''): string
{
    return __DIR__.'/Fixtures'.($name ? '/'.$name : '');
}
