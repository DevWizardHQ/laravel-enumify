<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

enum LabelsThrowing
{
    case BROKEN;

    public static function labels(): array
    {
        throw new \RuntimeException('Broken labels');
    }
}
