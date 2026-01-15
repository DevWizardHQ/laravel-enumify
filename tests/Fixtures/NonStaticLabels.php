<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

enum NonStaticLabels
{
    case ONE;

    public function labels(): array
    {
        return [
            'ONE' => 'One',
        ];
    }
}
