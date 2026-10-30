<?php

declare(strict_types=1);
use DevWizardHQ\Enumify\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

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
