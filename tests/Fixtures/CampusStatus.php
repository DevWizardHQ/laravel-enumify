<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Backed enum with custom methods (color, isActive, etc.).
 * This demonstrates full method extraction for TypeScript generation.
 */
enum CampusStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::INACTIVE => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::SUSPENDED => 'red',
            self::INACTIVE => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }

    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }

    public function canAccess(): bool
    {
        return $this === self::ACTIVE;
    }

    public function priority(): int
    {
        return match ($this) {
            self::ACTIVE => 1,
            self::SUSPENDED => 2,
            self::INACTIVE => 3,
        };
    }

    public function badge(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'primary',
            self::SUSPENDED => 'warning',
            self::INACTIVE => null,
        };
    }
}
