<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Backed enum with mixed-case keys for normalization testing.
 */
enum MixedCaseStatus: string
{
    case pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case CANCELLED = 'cancelled';

    public function isDefault(): bool
    {
        return $this === self::pending;
    }
}