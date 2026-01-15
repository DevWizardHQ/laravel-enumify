<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Data\EnumCaseDefinition;
use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;
use DevWizardHQ\Enumify\Services\TypeScriptGenerator;

beforeEach(function () {
    $this->generator = new TypeScriptGenerator(
        exportStyle: 'enum',
        generateUnionTypes: true,
        generateLabelMaps: true,
        generateMethodMaps: true,
    );
});

describe('TypeScriptGenerator', function () {
    it('generates basic enum export', function () {
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
            ->toContain('export enum OrderStatus {')
            ->toContain('Pending = "pending",')
            ->toContain('Shipped = "shipped",');
    });

    it('generates union type', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\OrderStatus',
            name: 'OrderStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('PENDING', 'pending'),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)->toContain('export type OrderStatusValue = `${OrderStatus}`;');
    });

    it('generates label map when labels exist', function () {
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
            ->toContain('export const PaymentMethodLabels: Record<PaymentMethod, string> = {')
            ->toContain('[PaymentMethod.CreditCard]: "Credit Card",')
            ->toContain('[PaymentMethod.Paypal]: "PayPal",');
    });

    it('humanizes missing labels when some labels are provided', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\ReminderStatus',
            name: 'ReminderStatus',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('PENDING_PAYMENT', 'pending_payment', 'Pending'),
                new EnumCaseDefinition('AWAITING_APPROVAL', 'awaiting_approval'),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('[ReminderStatus.PendingPayment]: "Pending",')
            ->toContain('[ReminderStatus.AwaitingApproval]: "Awaiting Approval",');
    });

    it('generates method maps for custom methods', function () {
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

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const CampusStatusColors: Record<CampusStatus, string> = {')
            ->toContain('[CampusStatus.Active]: "green",')
            ->toContain('[CampusStatus.Inactive]: "gray",');
    });

    it('generates boolean method maps with helper functions', function () {
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
                new EnumMethodDefinition('isActive', ['bool'], 'boolean', [
                    'ACTIVE' => true,
                    'INACTIVE' => false,
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const CampusStatusIsActive: Record<CampusStatus, boolean> = {')
            ->toContain('[CampusStatus.Active]: true,')
            ->toContain('[CampusStatus.Inactive]: false,')
            ->toContain('export function isActive(value: CampusStatus): boolean {')
            ->toContain('return CampusStatusIsActive[value];');
    });

    it('generates integer method maps', function () {
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
                new EnumMethodDefinition('priority', ['int'], 'number', [
                    'ACTIVE' => 1,
                    'INACTIVE' => 3,
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const CampusStatusPrioritys: Record<CampusStatus, number> = {')
            ->toContain('[CampusStatus.Active]: 1,')
            ->toContain('[CampusStatus.Inactive]: 3,');
    });

    it('generates nullable string method maps', function () {
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
                new EnumMethodDefinition('badge', ['string', 'null'], 'string | null', [
                    'ACTIVE' => 'primary',
                    'INACTIVE' => null,
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const CampusStatusBadges: Record<CampusStatus, string | null> = {')
            ->toContain('[CampusStatus.Active]: "primary",')
            ->toContain('[CampusStatus.Inactive]: null,');
    });

    it('escapes label strings', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Alert',
            name: 'Alert',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('WARNING', 'warning', "Line\nBreak \"Alert\""),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)->toContain('[Alert.Warning]: "Line\\nBreak \\"Alert\\"",');
    });

    it('renders non-scalar method values as null', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Payload',
            name: 'Payload',
            isBacked: true,
            backingType: 'string',
            cases: [
                new EnumCaseDefinition('DATA', 'data'),
            ],
            methods: [
                new EnumMethodDefinition('payload', ['string'], 'unknown', [
                    'DATA' => ['nested' => 'value'],
                ]),
            ],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('export const PayloadPayloads: Record<Payload, unknown> = {')
            ->toContain('[Payload.Data]: null,');
    });

    it('generates unit enum with case names as values', function () {
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
            ->toContain('Low = "LOW",')
            ->toContain('High = "HIGH",');
    });

    it('generates integer backed enum', function () {
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
            ->toContain('Ok = 200,')
            ->toContain('NotFound = 404,');
    });

    it('includes header comments', function () {
        $enum = new EnumDefinition(
            fqcn: 'App\Enums\Test',
            name: 'Test',
            isBacked: true,
            backingType: 'string',
            cases: [],
        );

        $output = $this->generator->generate($enum);

        expect($output)
            ->toContain('// This file is auto-generated by Laravel Enumify.')
            ->toContain('// Do not edit this file manually.')
            ->toContain('// @generated');
    });
});

describe('TypeScriptGenerator with const style', function () {
    beforeEach(function () {
        $this->constGenerator = new TypeScriptGenerator(
            exportStyle: 'const',
            generateUnionTypes: true,
            generateLabelMaps: true,
            generateMethodMaps: true,
        );
    });

    it('generates const export style', function () {
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

        $output = $this->constGenerator->generate($enum);

        expect($output)
            ->toContain('export const OrderStatus = {')
            ->toContain('Pending: "pending",')
            ->toContain('Shipped: "shipped",')
            ->toContain('} as const;')
            ->toContain('export type OrderStatus = typeof OrderStatus[keyof typeof OrderStatus];');
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
            ->toContain("export * from './order-status';")
            ->toContain("export * from './payment-method';");
    });

    it('uses correct file case for barrel exports', function () {
        $enums = [
            new EnumDefinition('App\Enums\OrderStatus', 'OrderStatus', true, 'string', []),
        ];

        $kebab = $this->generator->generateBarrel($enums, 'kebab');
        expect($kebab)->toContain("export * from './order-status';");

        $camel = $this->generator->generateBarrel($enums, 'camel');
        expect($camel)->toContain("export * from './orderStatus';");

        $pascal = $this->generator->generateBarrel($enums, 'pascal');
        expect($pascal)->toContain("export * from './OrderStatus';");
    });
});
