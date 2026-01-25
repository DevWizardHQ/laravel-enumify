# Laravel Enumify

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devwizardhq/laravel-enumify.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-enumify)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-enumify/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devwizardhq/laravel-enumify/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devwizardhq/laravel-enumify.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-enumify)
[![NPM Version](https://img.shields.io/npm/v/@devwizard/vite-plugin-enumify.svg?style=flat-square)](https://www.npmjs.com/package/@devwizard/vite-plugin-enumify)
[![NPM Downloads](https://img.shields.io/npm/dt/@devwizard/vite-plugin-enumify.svg?style=flat-square)](https://www.npmjs.com/package/@devwizard/vite-plugin-enumify)

**Auto-generate TypeScript enums from Laravel PHP enums. Refactor hardcoded values and normalize enum keys.**

Laravel Enumify keeps frontend TypeScript enums in sync with backend PHP enums automatically. It also scans your codebase for hardcoded enum values and can refactor them to use proper enum references. Includes tools for normalizing enum case names to UPPERCASE.

## Features

- 🔄 **Automatic Sync** – Runs during `npm run dev` and `npm run build` via the Vite plugin
- 🧭 **Wayfinder-Level DX** – One install command to scaffold everything
- 🏷️ **Labels Support** – `label()` or static `labels()` become TS maps
- 🎨 **Custom Methods** – Public zero-arg scalar methods become TS maps
- 📦 **Barrel Exports** – Optional `index.ts` for clean imports
- ⚡ **Smart Caching** – Only regenerate changed files using hashes
- 🔒 **Git-Friendly** – `.gitkeep` and strict `.gitignore` patterns supported
- 🔧 **Refactor Command** – Scan and fix hardcoded enum values in your codebase
- 🔠 **Key Normalization** – Convert enum keys to UPPERCASE and update all references

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Node.js 18+
- Vite 4, 5, 6, or 7

## Package Links

- [Composer](https://packagist.org/packages/devwizardhq/laravel-enumify)
- [NPM](https://www.npmjs.com/package/@devwizard/vite-plugin-enumify)

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

- Create `resources/js/enums/`
- Create `resources/js/enums/.gitkeep`
- Print the `.gitignore` lines to add (and offer to append them)
- Offer to publish the config file

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

**Prerequisites:** Install the appropriate localization package for your framework:

```bash
# For React
npm install @devwizard/laravel-localizer-react

# For Vue
npm install @devwizard/laravel-localizer-vue
```

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
 * CampusStatus enum methods (PHP-style)
 */
export function useCampusStatusUtils() {
    const { __ } = useLocalizer();

    return {
        label(status: CampusStatus): string {
            switch (status) {
                case CampusStatus.ACTIVE:
                    return __("Active");
                // ...
                default:
                    return status;
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

**Note:** If you enable localization mode but disable label generation (`generate_label_maps` set to `false`), the generator will create hooks/composables without the `useLocalizer` import, since labels are the only feature that uses localization. In this case, you'll get a hook that only contains custom methods and the `options()` method.

## Method Conversion Rules

Enumify will convert methods into TypeScript maps when they meet these rules:

- Public, non-static, zero-argument methods only
- Return types must be `string`, `int`, `float`, `bool`, or nullable/union combinations of those
- Methods without return types or unsupported return types are skipped
- Map naming: `EnumName + MethodName` (pluralized for non-boolean methods)
- Boolean methods also generate a helper function

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

### enumify:refactor

Scan your codebase for hardcoded enum values and refactor them to use proper enum references. Only columns with enum casts in models will be refactored. This command also supports normalizing enum case names to UPPERCASE and updating all references throughout your application.

#### Available Options

| Option             | Short | Description                                                      |
| ------------------ | ----- | ---------------------------------------------------------------- |
| `--fix`            | `-f`  | Apply refactoring changes to files                               |
| `--dry-run`        | `-d`  | Preview changes without modifying files                          |
| `--enum=`          | `-e`  | Target a specific enum class by short name (e.g., `OrderStatus`) |
| `--path=`          | `-p`  | Limit scan to a specific directory (e.g., `app/Models`)          |
| `--interactive`    | `-i`  | Run in interactive mode with guided prompts                      |
| `--json`           | `-j`  | Output results in JSON format                                    |
| `--backup`         |       | Create backups before applying changes                           |
| `--include=`       |       | File patterns to include (e.g., `*.php`)                         |
| `--exclude=`       |       | Paths or patterns to exclude from scanning                       |
| `--report=`        |       | Export report to file (formats: `json`, `csv`, `md`)             |
| `--detailed`       |       | Show detailed output with code context                           |
| `--normalize-keys` |       | Convert enum keys to UPPERCASE and fix all references            |

#### Scanning for Hardcoded Values

```bash
# Scan and display hardcoded enum values
php artisan enumify:refactor

# Preview what changes would be made
php artisan enumify:refactor --dry-run

# Apply fixes with backup
php artisan enumify:refactor --fix --backup

# Target a specific enum
php artisan enumify:refactor --enum=OrderStatus

# Limit scan to a directory
php artisan enumify:refactor --path=app/Services

# Export a markdown report
php artisan enumify:refactor --report=refactor-report.md
```

**Note:** The refactor command only processes columns that have an enum cast defined in a model. If no cast is available for a column, it will skip refactoring for that column.

#### Key Normalization (UPPERCASE)

The `--normalize-keys` flag converts enum case names from any case format to UPPERCASE and updates all references in your codebase:

- `case Active` → `case ACTIVE`
- `case pending` → `case PENDING`
- `case InProgress` → `case IN_PROGRESS`

```bash
# Preview key normalization changes
php artisan enumify:refactor --normalize-keys --dry-run

# Apply key normalization with backup
php artisan enumify:refactor --normalize-keys --fix --backup
```

#### Interactive Mode

Run the command interactively with guided prompts:

```bash
php artisan enumify:refactor --interactive
```

This mode allows you to:

- Choose between scan, preview, apply, or normalize modes
- Select which enums to check
- Specify the directory to scan
- Confirm changes before applying

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

- `resources/js/enums/*.ts` – one file per enum
- `resources/js/enums/index.ts` – barrel exports (optional)
- `resources/js/enums/.enumify-manifest.json` – hashes, timestamps, versions

The generator uses atomic writes and skips unchanged files for speed.

## Local Development (Monorepo)

This repo contains two packages:

- `packages/laravel-enumify` (Composer)
- `packages/vite-plugin-enumify` (NPM)

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

- PHP: `composer test` and `composer test-coverage`
- Node: `pnpm run build && pnpm run typecheck`

## Troubleshooting

- **Missing enums folder**: run `php artisan enumify:install` or ensure `resources/js/enums/.gitkeep` exists.
- **Imports fail during build**: ensure the Vite plugin is enabled and runs before `laravel()`.
- **Enums not discovered**: check `config/enumify.php` paths and include/exclude filters.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md)

## License

MIT. See [LICENSE.md](./LICENSE.md)
