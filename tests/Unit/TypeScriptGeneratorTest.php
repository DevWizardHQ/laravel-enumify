<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Data\EnumCaseDefinition;
use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;
use DevWizardHQ\Enumify\Services\TypeScriptGenerator;

beforeEach(function () {
    $this->generator = new TypeScriptGenerator;
});

describe('TypeScriptGenerator', function () {
    it('throws exception for invalid localization mode', function () {
        expect(fn () => new TypeScriptGenerator(localizationMode: 'invalid'))
            ->toThrow(\InvalidArgumentException::class, 'Invalid localization mode "invalid". Allowed values are: none, react, vue.');
    });

    it('generates basic const export and utils', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\OrderStatus',
            name: 'OrderStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('PENDING', 'pending'),
                new EnumCaseDefinition('SHIPPED', 'shipped'),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('// AUTO-GENERATED — DO NOT EDIT MANUALLY')
            ->toContain('export const OrderStatus = {')
            ->toContain("  PENDING: 'pending',")
            ->toContain("  SHIPPED: 'shipped',")
            ->toContain('} as const;')
            ->toContain('export type OrderStatus =')
            ->toContain('  typeof OrderStatus[keyof typeof OrderStatus];')
            ->toContain('export const OrderStatusUtils = {')
            ->toContain('  options(): OrderStatus[] {')
            ->toContain('    return Object.values(OrderStatus);');
    });

    it('generates label method in utils when specific labels exist', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\PaymentMethod',
            name: 'PaymentMethod',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('CREDIT_CARD', 'credit_card', 'Credit Card'),
                new EnumCaseDefinition('PAYPAL', 'paypal', 'PayPal'),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('label(status: PaymentMethod): string {')
            ->toContain('switch (status) {')
            ->toContain('case PaymentMethod.CREDIT_CARD:')
            ->toContain("return 'Credit Card';")
            ->toContain('case PaymentMethod.PAYPAL:')
            ->toContain("return 'PayPal';");
    });

    it('humanizes missing labels in label method', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\ReminderStatus',
            name: 'ReminderStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('SENT', 'sent', 'Sent'),
                new EnumCaseDefinition('PENDING_PAYMENT', 'pending_payment'),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('case ReminderStatus.PENDING_PAYMENT:')
            ->toContain("return 'Pending Payment';");
    });

    it('generates custom methods in utils', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\CampusStatus',
            name: 'CampusStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active'),
                new EnumCaseDefinition('INACTIVE', 'inactive'),
            ],
            methods: [
                new EnumMethodDefinition('color', ['string'], 'string', [
                    'ACTIVE' => 'green',
                    'INACTIVE' => 'gray',
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('color(status: CampusStatus): string {')
            ->toContain('switch (status) {')
            ->toContain('case CampusStatus.ACTIVE:')
            ->toContain("return 'green';")
            ->toContain('case CampusStatus.INACTIVE:')
            ->toContain("return 'gray';");
    });

    it('generates optimized boolean methods in utils', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\CampusStatus',
            name: 'CampusStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active'),
                new EnumCaseDefinition('INACTIVE', 'inactive'),
                new EnumCaseDefinition('SUSPENDED', 'suspended'),
            ],
            methods: [
                new EnumMethodDefinition('isActive', ['bool'], 'boolean', [
                    'ACTIVE' => true,
                    'INACTIVE' => false,
                    'SUSPENDED' => false,
                ]),
                new EnumMethodDefinition('isInactiveOrSuspended', ['bool'], 'boolean', [
                    'ACTIVE' => false,
                    'INACTIVE' => true,
                    'SUSPENDED' => true,
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('isActive(status: CampusStatus): boolean {')
            ->toContain('return status === CampusStatus.ACTIVE;')
            ->toContain('isInactiveOrSuspended(status: CampusStatus): boolean {')
            ->toContain('return status === CampusStatus.INACTIVE || status === CampusStatus.SUSPENDED;');
    });

    it('generates false return for boolean method with no true values', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\CampusStatus',
            name: 'CampusStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active'),
                new EnumCaseDefinition('INACTIVE', 'inactive'),
            ],
            methods: [
                new EnumMethodDefinition('isSomethingImpossible', ['bool'], 'boolean', [
                    'ACTIVE' => false,
                    'INACTIVE' => false,
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('isSomethingImpossible(status: CampusStatus): boolean {')
            ->toContain('return false;');
    });

    it('formats mixed values using switch including booleans and fallbacks', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\MixedStatus',
            name: 'MixedStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('YES', 'yes'),
                new EnumCaseDefinition('NO', 'no'),
                new EnumCaseDefinition('UNKNOWN', 'unknown'),
            ],
            methods: [
                new EnumMethodDefinition('isMixed', ['mixed'], 'boolean | null', [
                    'YES' => true,
                    'NO' => false,
                    'UNKNOWN' => [],  // Should fallback to null
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('case MixedStatus.YES:')
            ->toContain('return true;')
            ->toContain('case MixedStatus.NO:')
            ->toContain('return false;')
            ->toContain('case MixedStatus.UNKNOWN:')
            ->toContain('return null;');
    });

    it('generates integer backed enum as const', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\HttpStatus',
            name: 'HttpStatus',
            isBacked: true,
            backingType: 'int',
            cases: [
                new EnumCaseDefinition('OK', 200),
                new EnumCaseDefinition('NOT_FOUND', 404),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const HttpStatus = {')
            ->toContain('  OK: 200,')
            ->toContain('  NOT_FOUND: 404,');
    });

    it('generates unit enum with names as values', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Priority',
            name: 'Priority',
            isBacked: false,
            backingType: null,
            cases: [
                new EnumCaseDefinition('LOW', null),
                new EnumCaseDefinition('HIGH', null),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const Priority = {')
            ->toContain("  LOW: 'LOW',")
            ->toContain("  HIGH: 'HIGH',");
    });

    it('escapes strings properly', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Text',
            name: 'Text',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('QUOTE', "quo'te"),
            ],
        );

        $output = $this->generator->generate($enum);
        expect($output)->toContain("QUOTE: 'quo\'te'");
    });

    it('generates localization hook and wrappers for React', function () {
        $generator = new TypeScriptGenerator(localizationMode: 'react');

        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active', 'Active Status'),
            ],
        );

        $output = $generator->generate($enum);

        expect($output)
            ->toContain("import { useLocalizer } from '@devwizard/laravel-localizer-react';")
            ->toContain('export function useStatusUtils() {')
            ->toContain('    const { __ } = useLocalizer();')
            ->toContain('    return {')
            ->toContain("                    return __('Active Status');");
    });

    it('generates localization hook and wrappers for Vue', function () {
        $generator = new TypeScriptGenerator(localizationMode: 'vue');

        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active', 'Active Status'),
            ],
        );

        $output = $generator->generate($enum);

        expect($output)
            ->toContain("import { useLocalizer } from '@devwizard/laravel-localizer-vue';")
            ->toContain('export function useStatusUtils() {')
            ->toContain('    const { __ } = useLocalizer();')
            ->toContain('    return {')
            ->toContain("                    return __('Active Status');");
    });

    it('generates localization hook with custom methods for React', function () {
        $generator = new TypeScriptGenerator(localizationMode: 'react');

        $enum = new EnumDefinition(
            fqcn: 'App\Enums\CampusStatus',
            name: 'CampusStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active', 'Active'),
                new EnumCaseDefinition('INACTIVE', 'inactive', 'Inactive'),
            ],
            methods: [
                new EnumMethodDefinition('color', ['string'], 'string', [
                    'ACTIVE' => 'green',
                    'INACTIVE' => 'gray',
                ]),
                new EnumMethodDefinition('isActive', ['bool'], 'boolean', [
                    'ACTIVE' => true,
                    'INACTIVE' => false,
                ]),
            ],
        );

        $output = $generator->generate($enum);

        expect($output)
            ->toContain("import { useLocalizer } from '@devwizard/laravel-localizer-react';")
            ->toContain('export function useCampusStatusUtils() {')
            ->toContain('    const { __ } = useLocalizer();')
            ->toContain('        color(status: CampusStatus): string {')
            ->toContain('            switch (status) {')
            ->toContain('                case CampusStatus.ACTIVE:')
            ->toContain("                    return 'green';")
            ->toContain('        isActive(status: CampusStatus): boolean {')
            ->toContain('            return status === CampusStatus.ACTIVE;');
    });

    it('generates localization hook with custom methods for Vue', function () {
        $generator = new TypeScriptGenerator(localizationMode: 'vue');

        $enum = new EnumDefinition(
            fqcn: 'App\Enums\CampusStatus',
            name: 'CampusStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active', 'Active'),
                new EnumCaseDefinition('INACTIVE', 'inactive', 'Inactive'),
            ],
            methods: [
                new EnumMethodDefinition('color', ['string'], 'string', [
                    'ACTIVE' => 'green',
                    'INACTIVE' => 'gray',
                ]),
            ],
        );

        $output = $generator->generate($enum);

        expect($output)
            ->toContain("import { useLocalizer } from '@devwizard/laravel-localizer-vue';")
            ->toContain('export function useCampusStatusUtils() {')
            ->toContain('    const { __ } = useLocalizer();')
            ->toContain('        color(status: CampusStatus): string {')
            ->toContain('            switch (status) {')
            ->toContain('                case CampusStatus.ACTIVE:')
            ->toContain("                    return 'green';");
    });

    it('does not import useLocalizer when localization is enabled but no labels exist', function () {
        $generator = new TypeScriptGenerator(localizationMode: 'react');

        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Status',
            name: 'Status',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('ACTIVE', 'active'),
            ],
            methods: [
                new EnumMethodDefinition('color', ['string'], 'string', [
                    'ACTIVE' => 'green',
                ]),
            ],
        );

        $output = $generator->generate($enum);

        expect($output)
            ->not->toContain("import { useLocalizer } from '@devwizard/laravel-localizer-react';")
            ->not->toContain('const { __ } = useLocalizer();')
            ->toContain('export function useStatusUtils() {')
            ->toContain('    return {')
            ->toContain('        color(status: Status): string {');
    });
});

describe('TypeScriptGenerator barrel', function () {
    it('generates barrel index file', function () {
        $enums = [
            new EnumDefinition('App\Enums\OrderStatus', 'OrderStatus', true, 'string', []),
            new EnumDefinition('App\Enums\PaymentMethod', 'PaymentMethod', true, 'string', []),
        ];

        $output = $this->generator->generateBarrel($enums, 'kebab');

        expect($output)
            ->toContain('// AUTO-GENERATED — DO NOT EDIT MANUALLY')
            ->toContain("export * from './order-status';")
            ->toContain("export * from './payment-method';");
    });
});
