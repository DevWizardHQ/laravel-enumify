# Laravel Enumify

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devwizardhq/laravel-enumify.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-enumify)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-enumify/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devwizardhq/laravel-enumify/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devwizardhq/laravel-enumify.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-enumify)
[![NPM](https://img.shields.io/npm/v/@devwizard/vite-plugin-enumify.svg?style=flat-square)](https://www.npmjs.com/package/@devwizard/vite-plugin-enumify)

**Auto-generate TypeScript enums and maps from Laravel PHP enums, with Vite integration.**

Laravel Enumify keeps frontend TypeScript enums in sync with backend PHP enums automatically. It generates files before Vite compiles, so imports never fail and no runtime fetching is needed.

## Features

-   🔄 **Automatic Sync** – Runs during `npm run dev` and `npm run build` via the Vite plugin
-   🧭 **Wayfinder-Level DX** – One install command to scaffold everything
-   🏷️ **Labels Support** – `label()` or static `labels()` become TS maps
-   🎨 **Custom Methods** – Public zero-arg scalar methods become TS maps
-   📦 **Barrel Exports** – Optional `index.ts` for clean imports
-   ⚡ **Smart Caching** – Only regenerate changed files using hashes
-   🔒 **Git-Friendly** – `.gitkeep` and strict `.gitignore` patterns supported

## Requirements

-   PHP 8.4+
-   Laravel 12
-   Node.js 18+
-   Vite 4, 5, 6, or 7

## Package Links

-   Composer (Packagist): https://packagist.org/packages/devwizardhq/laravel-enumify
-   NPM: https://www.npmjs.com/package/@devwizard/vite-plugin-enumify
-   Repository: https://github.com/devwizardhq/laravel-enumify

## Installation

### 1) Install the Laravel package

```bash
composer require devwizardhq/laravel-enumify
```

### 2) Run the install command

```bash
php artisan enumify:install
```

This will:

-   Create `resources/js/enums/`
-   Create `resources/js/enums/.gitkeep`
-   Print the `.gitignore` lines to add (and offer to append them)
-   Offer to publish the config file

### 3) Configure Vite

If the installer successfully installed the plugin, you just need to add it to your `vite.config.js`:

```ts
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import enumify from "@devwizard/vite-plugin-enumify";

export default defineConfig({
    plugins: [
        enumify(),
        laravel({
            input: ["resources/js/app.ts"],
            refresh: true,
        }),
    ],
});
```

If the automatic installation skipped or failed, manually install the plugin:

```bash
npm install @devwizard/vite-plugin-enumify --save-dev
# or
pnpm add -D @devwizard/vite-plugin-enumify
# or
yarn add -D @devwizard/vite-plugin-enumify
```

## Usage

### Basic Enum

```php
// app/Enums/OrderStatus.php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
}
```

**Generated TypeScript:**

```ts
// resources/js/enums/order-status.ts
export const OrderStatus = {
    PENDING: "pending",
    PROCESSING: "processing",
    SHIPPED: "shipped",
    DELIVERED: "delivered",
} as const;

export type OrderStatus = (typeof OrderStatus)[keyof typeof OrderStatus];

export const OrderStatusUtils = {
    options(): OrderStatus[] {
        return Object.values(OrderStatus);
    },
};
```

### With Labels & Custom Methods

```php
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
}
```

**Generated TypeScript:**

```ts
export const CampusStatus = {
    ACTIVE: "active",
    SUSPENDED: "suspended",
    INACTIVE: "inactive",
} as const;

export type CampusStatus = (typeof CampusStatus)[keyof typeof CampusStatus];

export const CampusStatusUtils = {
    label(status: CampusStatus): string {
        switch (status) {
            case CampusStatus.ACTIVE:
                return "Active";
            case CampusStatus.SUSPENDED:
                return "Suspended";
            case CampusStatus.INACTIVE:
                return "Inactive";
        }
    },

    color(status: CampusStatus): string {
        switch (status) {
            case CampusStatus.ACTIVE:
                return "green";
            case CampusStatus.SUSPENDED:
                return "red";
            case CampusStatus.INACTIVE:
                return "gray";
        }
    },

    isActive(status: CampusStatus): boolean {
        return status === CampusStatus.ACTIVE;
    },

    options(): CampusStatus[] {
        return Object.values(CampusStatus);
    },
};
```

### Frontend Usage

```ts
import { CampusStatus, CampusStatusUtils } from "@/enums/campus-status";

const status: CampusStatus = CampusStatus.ACTIVE;

// Get label
console.log(CampusStatusUtils.label(status)); // 'Active'

// Get custom method value
const badgeColor = CampusStatusUtils.color(status); // 'green'

// Check state (boolean method)
if (CampusStatusUtils.isActive(status)) {
    // Allow access
}

// Get all options (e.g., for a dropdown)
const options = CampusStatusUtils.options();
```

### Localization

Enumify supports automatic localization for React and Vue applications using `@devwizard/laravel-localizer-react` or `@devwizard/laravel-localizer-vue`.

1. **Configure the mode** in `config/enumify.php`:

```php
'localization' => [
    'mode' => 'react', // 'react' | 'vue' | 'none'
],
```

2. **Generated TypeScript** will export a **use{Enum}Utils** hook instead of a static object:

```ts
import { useLocalizer } from "@devwizard/laravel-localizer-react";

export const CampusStatus = {
    ACTIVE: "active",
    // ...
} as const;

/**
 * React Hook for CampusStatus utils
 */
export function useCampusStatusUtils() {
    const { __ } = useLocalizer();

    return {
        label(status: CampusStatus): string {
            switch (status) {
                case CampusStatus.ACTIVE:
                    return __("Active");
                // ...
            }
        },

        options(): CampusStatus[] {
            return Object.values(CampusStatus);
        },
    };
}
```

3. **Usage in Components**:

```tsx
import { CampusStatus, useCampusStatusUtils } from "@/enums/campus-status";

function MyComponent() {
    const { label, options } = useCampusStatusUtils();

    return (
        <select>
            {options().map((status) => (
                <option value={status}>{label(status)}</option>
            ))}
        </select>
    );
}
```

This ensures your enums are fully localized on the frontend while respecting React's Rules of Hooks.

## Method Conversion Rules

Enumify will convert methods into TypeScript maps when they meet these rules:

-   Public, non-static, zero-argument methods only
-   Return types must be `string`, `int`, `float`, `bool`, or nullable/union combinations of those
-   Methods without return types or unsupported return types are skipped
-   Map naming: `EnumName + MethodName` (pluralized for non-boolean methods)
-   Boolean methods also generate a helper function

Labels are handled separately using `label()` or `labels()`.

## Artisan Commands

### enumify:install

```bash
php artisan enumify:install
```

### enumify:sync

```bash
# Standard sync
php artisan enumify:sync

# Force regenerate all files
php artisan enumify:sync --force

# Preview changes without writing
php artisan enumify:sync --dry-run

# Sync only one enum
php artisan enumify:sync --only="App\Enums\OrderStatus"

# Output as JSON
php artisan enumify:sync --format=json

# Suppress console output (useful for Vite)
php artisan enumify:sync --quiet
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="enumify-config"
```

```php
// config/enumify.php
return [
    'paths' => [
        'enums' => ['app/Enums'],
        'output' => 'resources/js/enums',
    ],

    'naming' => [
        'file_case' => 'kebab',
    ],

    'features' => [
        'generate_union_types' => true,
        'generate_label_maps' => true,
        'generate_method_maps' => true,
        'generate_index_barrel' => true,
    ],

    'runtime' => [
        'watch' => true,
    ],

    'filters' => [
        'include' => [],
        'exclude' => [],
    ],
];
```

## Generated Output

-   `resources/js/enums/*.ts` – one file per enum
-   `resources/js/enums/index.ts` – barrel exports (optional)
-   `resources/js/enums/.enumify-manifest.json` – hashes, timestamps, versions

The generator uses atomic writes and skips unchanged files for speed.

## Local Development (Monorepo)

This repo contains two packages:

-   `packages/laravel-enumify` (Composer)
-   `packages/vite-plugin-enumify` (NPM)

### Composer path repository

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-enumify",
            "options": { "symlink": true }
        }
    ]
}
```

### Vite plugin workspace

You can install the Vite plugin locally with a workspace or a file path:

```bash
pnpm add -D ./packages/vite-plugin-enumify
# or
npm install --save-dev ./packages/vite-plugin-enumify
```

## Git Workflow

Recommended workflow for both packages:

1. Create a feature branch from `main`
2. Make changes with focused commits
3. Run tests/builds locally
4. Open a PR and ensure CI passes

Release tip: tag releases after merging to `main`, then publish to Packagist and NPM.

## CI

Suggested pipelines:

-   PHP: `composer test` and `composer test-coverage`
-   Node: `pnpm run build && pnpm run typecheck`

## Troubleshooting

-   **Missing enums folder**: run `php artisan enumify:install` or ensure `resources/js/enums/.gitkeep` exists.
-   **Imports fail during build**: ensure the Vite plugin is enabled and runs before `laravel()`.
-   **Enums not discovered**: check `config/enumify.php` paths and include/exclude filters.

## Changelog

See `CHANGELOG.md`.

## License

MIT. See `LICENSE.md`.
