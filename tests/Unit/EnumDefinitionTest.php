<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;

describe('EnumDefinition', function () {
    it('generates filename with different cases', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\OrderStatus',
            name: 'OrderStatus',
            isBacked: true,
            backingType: 'string',
            cases: [],
        );

        expect($enum->getFilename('kebab'))->toBe('order-status');
        expect($enum->getFilename('camel'))->toBe('orderStatus');
        expect($enum->getFilename('pascal'))->toBe('OrderStatus');
    });

    it('identifies if it has methods', function () {
        $enumWithMethods = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [],
            methods: [
                new EnumMethodDefinition('color', ['string'], 'string', []),
            ],
        );

        $enumWithoutMethods = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [],
            methods: [],
        );

        expect($enumWithMethods->hasMethods())->toBeTrue();
        expect($enumWithoutMethods->hasMethods())->toBeFalse();
    });
});
