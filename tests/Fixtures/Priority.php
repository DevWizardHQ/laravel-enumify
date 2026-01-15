<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Unit enum (no backing type).
 */
enum Priority
{
    case LOW;
    case MEDIUM;
    case HIGH;
    case CRITICAL;
}
