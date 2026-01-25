<?php

declare(strict_types=1);

return [
    /*
     * |--------------------------------------------------------------------------
     * | Paths Configuration
     * |--------------------------------------------------------------------------
     * |
     * | Define where to scan for PHP enums and where to output TypeScript files.
     * |
     */
    'paths' => [
        // Directories to scan for PHP enums (relative to base_path)
        'enums' => ['app/Enums'],
        // Output directory for generated TypeScript files (relative to base_path)
        'output' => 'resources/js/enums',
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Naming Conventions
     * |--------------------------------------------------------------------------
     * |
     * | Control how generated files and exports are named.
     * |
     */
    'naming' => [
        // File naming case: 'kebab' | 'camel' | 'pascal'
        // kebab: order-status.ts
        // camel: orderStatus.ts
        // pascal: OrderStatus.ts
        'file_case' => 'kebab',
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Feature Toggles
     * |--------------------------------------------------------------------------
     * |
     * | Enable or disable optional features.
     * |
     */
    'features' => [
        // Generate union type for each enum
        // export type OrderStatusValue = `${OrderStatus}`;
        'generate_union_types' => true,
        // Generate label maps if enum implements label() or labels()
        // export const OrderStatusLabels: Record<OrderStatus, string> = { ... };
        'generate_label_maps' => true,
        // Generate maps for all custom enum methods (color(), isActive(), etc.)
        // export const OrderStatusColors: Record<OrderStatus, string> = { ... };
        // export const OrderStatusIsActive: Record<OrderStatus, boolean> = { ... };
        // Also generates helper functions for boolean methods:
        // export function isActive(value: OrderStatus): boolean { ... }
        'generate_method_maps' => true,
        // Generate index.ts barrel file for easy imports
        // export * from './order-status';
        'generate_index_barrel' => true,
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Localization
     * |--------------------------------------------------------------------------
     * |
     * | Configure how generated enums handle localization.
     * |
     */
    'localization' => [
        // Mode: 'none' | 'react' | 'vue'
        // none: No localization (default)
        // react: Uses @devwizard/laravel-localizer-react
        // vue: Uses @devwizard/laravel-localizer-vue
        'mode' => 'none',
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Runtime Configuration
     * |--------------------------------------------------------------------------
     * |
     * | Options that affect runtime behavior, primarily for the Vite plugin.
     * |
     */
    'runtime' => [
        // Enable file watching in development mode
        'watch' => true,
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Filters
     * |--------------------------------------------------------------------------
     * |
     * | Include or exclude specific enums from generation.
     * | Supports FQCN or glob patterns.
     * |
     * | Examples:
     * |   include: ['App\\Enums\\Status*', 'App\\Enums\\OrderStatus']
     * |   exclude: ['App\\Enums\\Internal\\*']
     * |
     */
    'filters' => [
        // Only include enums matching these patterns (empty = include all)
        'include' => [],
        // Exclude enums matching these patterns
        'exclude' => [],
    ],

    /*
     * |--------------------------------------------------------------------------
     * | Refactor Command Configuration
     * |--------------------------------------------------------------------------
     * |
     * | Configuration for the enumify:refactor command.
     * |
     */
    'refactor' => [
        // Default paths to exclude from scanning
        'exclude' => [
            'vendor',
            'node_modules',
            'storage',
            '.git',
            'bootstrap/cache',
            'public',
        ],
    ],
];
