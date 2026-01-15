<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Backed enum with string values.
 */
enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}
