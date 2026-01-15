<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Contracts;

/**
 * Interface for enums that provide human-readable labels.
 *
 * Implement this interface on your PHP enum to enable
 * automatic label map generation in TypeScript.
 */
interface HasLabels
{
    /**
     * Get the human-readable label for this enum case.
     */
    public function label(): string;
}
