<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

use DevWizardHQ\Enumify\Contracts\HasLabels;

enum LabelThrows implements HasLabels
{
    case FAIL;

    public function label(): string
    {
        throw new \RuntimeException('Label failed');
    }
}
