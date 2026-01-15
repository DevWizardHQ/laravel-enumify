<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

enum MethodEdgeCases
{
    case ONE;

    public function noReturnType()
    {
        return 'skip';
    }

    public function withParams(string $value): string
    {
        return $value;
    }

    public static function staticMethod(): string
    {
        return 'skip';
    }

    public function throws(): string
    {
        throw new \RuntimeException('boom');
    }

    public function unionNumbers(): int|float
    {
        return 1;
    }
}
