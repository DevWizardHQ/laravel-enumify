<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

enum LabelNonString
{
    case VALUE;

    public function label(): string|int
    {
        return 123;
    }

    public static function labels(): array
    {
        return [
            'VALUE' => 'Value',
        ];
    }
}
