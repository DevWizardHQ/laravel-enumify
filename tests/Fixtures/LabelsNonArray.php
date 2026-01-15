<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

enum LabelsNonArray
{
    case VALUE;

    public static function labels(): string
    {
        return 'invalid';
    }
}
