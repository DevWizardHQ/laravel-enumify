<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

/**
 * Fixture: Backed enum with integer values.
 */
enum HttpStatus: int
{
    case OK = 200;
    case CREATED = 201;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case NOT_FOUND = 404;
    case SERVER_ERROR = 500;
}
