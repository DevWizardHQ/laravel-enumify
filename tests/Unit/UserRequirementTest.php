<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Data\EnumCaseDefinition;
use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;
use DevWizardHQ\Enumify\Services\TypeScriptGenerator;

test('it matches the specific CampusStatus requirement', function () {
    $generator = new TypeScriptGenerator();

    $enum = new EnumDefinition(
        fqcn: 'App\Enums\CampusStatus',
        name: 'CampusStatus',
        isBacked: true,
        backingType: 'string',
        cases: [
            new EnumCaseDefinition('ACTIVE', 'active', 'Active'),
            new EnumCaseDefinition('SUSPENDED', 'suspended', 'Suspended'),
            new EnumCaseDefinition('INACTIVE', 'inactive', 'Inactive'),
        ],
        methods: [
            new EnumMethodDefinition('color', ['string'], 'string', [
                'ACTIVE' => 'green',
                'SUSPENDED' => 'red',
                'INACTIVE' => 'gray',
            ]),
            new EnumMethodDefinition('isActive', ['bool'], 'boolean', [
                'ACTIVE' => true,
                'SUSPENDED' => false,
                'INACTIVE' => false,
            ]),
            new EnumMethodDefinition('isSuspended', ['bool'], 'boolean', [
                'ACTIVE' => false,
                'SUSPENDED' => true,
                'INACTIVE' => false,
            ]),
            new EnumMethodDefinition('isInactive', ['bool'], 'boolean', [
                'ACTIVE' => false,
                'SUSPENDED' => false,
                'INACTIVE' => true,
            ]),
            new EnumMethodDefinition('canAccess', ['bool'], 'boolean', [
                'ACTIVE' => true,
                'SUSPENDED' => false,
                'INACTIVE' => false,  // Assuming implies false for others based on typical logic
            ]),
        ]
    );

    $output = $generator->generate($enum);

    // Verify Enum Definition
    expect($output)
        ->toContain('export const CampusStatus = {')
        ->toContain("  ACTIVE: 'active',")
        ->toContain("  SUSPENDED: 'suspended',")
        ->toContain("  INACTIVE: 'inactive',")
        ->toContain('} as const;')
        ->toContain('export type CampusStatus =')
        ->toContain('  typeof CampusStatus[keyof typeof CampusStatus];');

    // Verify Utils Object
    expect($output)->toContain('export const CampusStatusUtils = {');

    // Verify label()
    expect($output)
        ->toContain('label(status: CampusStatus): string {')
        ->toContain('switch (status) {')
        ->toContain('case CampusStatus.ACTIVE:')
        ->toContain("return 'Active';");

    // Verify color()
    expect($output)
        ->toContain('color(status: CampusStatus): string {')
        ->toContain('switch (status) {')
        ->toContain('case CampusStatus.ACTIVE:')
        ->toContain("return 'green';")
        ->toContain('case CampusStatus.SUSPENDED:')
        ->toContain("return 'red';");

    // Verify boolean methods (optimized)
    expect($output)
        ->toContain('isActive(status: CampusStatus): boolean {')
        ->toContain('return status === CampusStatus.ACTIVE;')
        ->toContain('isSuspended(status: CampusStatus): boolean {')
        ->toContain('return status === CampusStatus.SUSPENDED;')
        ->toContain('isInactive(status: CampusStatus): boolean {')
        ->toContain('return status === CampusStatus.INACTIVE;')
        ->toContain('canAccess(status: CampusStatus): boolean {')
        ->toContain('return status === CampusStatus.ACTIVE;');

    // Verify options()
    expect($output)
        ->toContain('options(): CampusStatus[] {')
        ->toContain('return Object.values(CampusStatus);');
});
