<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

enum LabelsWithEnumKeys
{
    case PRIMARY;
    case SECONDARY;

    public static function labels(): array
    {
        return [
            'PRIMARY' => 'Primary',
            'SECONDARY' => 'Secondary',
        ];
    }
}
