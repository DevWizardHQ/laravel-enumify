<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Backed enum with static labels() method.
 */
enum UserRole: string
{
    case ADMIN = 'admin';
    case MODERATOR = 'moderator';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    /**
     * Get human-readable labels for all cases.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'ADMIN' => 'Administrator',
            'MODERATOR' => 'Moderator',
            'EDITOR' => 'Content Editor',
            'VIEWER' => 'Viewer',
        ];
    }
}
