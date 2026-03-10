---
name: enumify
description: Generate TypeScript enums from PHP enums, sync enum values between backend and frontend, and refactor hardcoded enum strings.
---

# Enumify — PHP-to-TypeScript Enum Sync

## When to use this skill

Activate this skill when:

- Creating or modifying PHP enums in `app/Enums/`
- A model uses enum casting
- Building frontend UI that displays enum values, labels, colors, or badges
- Comparing or filtering by enum values in queries or Blade templates
- Writing TypeScript/JavaScript that references status, type, role, or category values

## Strict Rules

ALWAYS follow these rules when implementing with Enumify:

### Backend Rules

1. **ALWAYS place enums in `app/Enums/` by default**. This is the default scanned directory configured via `enumify.paths.enums` in `config/enumify.php`. If you customize that config, ensure your enums live in one of the configured paths; enums outside those paths will not be discovered.

2. **ALWAYS use `SCREAMING_SNAKE_CASE` for case names**:

    ```php
    // CORRECT
    case PENDING_PAYMENT = 'pending_payment';
    case IN_PROGRESS = 'in_progress';

    // WRONG — will cause inconsistency with TypeScript output
    case pendingPayment = 'pending_payment';
    case Pending = 'pending';
    ```

3. **ALWAYS use string-backed enums** when the value will be stored in a database or sent to a frontend. Use integer-backed enums only for numeric codes. Use unit enums only for purely internal logic.

4. **ALWAYS cast enums in models** when a column stores an enum value:

    ```php
    // In your model
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'role' => UserRole::class,
        ];
    }
    ```

5. **NEVER hardcode enum string values** in queries, comparisons, or assignments. Always reference the enum class:

    ```php
    // CORRECT
    $orders = Order::where('status', OrderStatus::PENDING)->get();
    $order->status = OrderStatus::SHIPPED;
    if ($order->status === OrderStatus::DELIVERED) { ... }

    // WRONG — defeats the purpose of enums
    $orders = Order::where('status', 'pending')->get();
    $order->status = 'shipped';
    if ($order->status === 'delivered') { ... }
    ```

6. **NEVER hardcode enum values in validation**. Use `Rule::enum()`:

    ```php
    // CORRECT
    'status' => ['required', Rule::enum(OrderStatus::class)],

    // WRONG
    'status' => ['required', Rule::in(['pending', 'shipped', 'delivered'])],
    ```

7. **ALWAYS implement `HasLabels`** when an enum needs human-readable display text. Use `match()` for the `label()` method:

    ```php
    use DevWizardHQ\Enumify\Concerns\EnumHelpers;
    use DevWizardHQ\Enumify\Contracts\HasLabels;

    enum OrderStatus: string implements HasLabels
    {
        use EnumHelpers;

        case PENDING = 'pending';
        case IN_PROGRESS = 'in_progress';
        case SHIPPED = 'shipped';

        public function label(): string
        {
            return match ($this) {
                self::PENDING => 'Pending',
                self::IN_PROGRESS => 'In Progress',
                self::SHIPPED => 'Shipped',
            };
        }
    }
    ```

8. **ALWAYS cover every case** in `match()` for `label()` and custom methods. Missing cases can lead to incorrect or missing labels in generated TypeScript (Enumify falls back to humanized case names) and may cause custom methods to be silently skipped during generation.

9. **Custom methods MUST follow these constraints** or they will be silently skipped during TypeScript generation:
    - MUST be `public` (not `protected` or `private`)
    - MUST NOT be `static`
    - MUST have zero required parameters
    - MUST declare a return type
    - Return type MUST be `string`, `int`, `float`, `bool`, `null`, or nullable/union of these
    - MUST NOT throw exceptions on any case

10. **ALWAYS use the `EnumHelpers` trait** on backed enums to get utility methods for free:

    ```php
    use DevWizardHQ\Enumify\Concerns\EnumHelpers;

    enum OrderStatus: string implements HasLabels
    {
        use EnumHelpers;

        case PENDING = 'pending';
        case SHIPPED = 'shipped';
        // ...
    }
    ```

    The trait provides these static methods:
    - `OrderStatus::options()` — returns `['pending' => 'Pending', 'shipped' => 'Shipped']` (value => label pairs, uses the `label()` method by default)
    - `OrderStatus::options('color')` — same format but calls `color()` instead of `label()`
    - `OrderStatus::selectOptions()` — returns `[['value' => 'pending', 'label' => 'Pending'], ...]` (ideal for frontend `<select>` dropdowns via Inertia)
    - `OrderStatus::values()` — returns `['pending', 'shipped']` (all raw values)
    - `OrderStatus::names()` — returns `['PENDING', 'SHIPPED']` (all case names)
    - `OrderStatus::hasValue('pending')` — returns `true` if the value exists in the enum

    Use these helpers instead of manually looping over `::cases()`:

    ```php
    // CORRECT — use the trait helpers
    $options = OrderStatus::options();
    $selectData = OrderStatus::selectOptions();
    if (OrderStatus::hasValue($input)) { ... }
    $validValues = OrderStatus::values();

    // WRONG — manual loops that the trait already handles
    $options = [];
    foreach (OrderStatus::cases() as $case) {
        $options[$case->value] = $case->label();
    }
    ```

11. **ALWAYS run `php artisan enumify:sync`** after creating or modifying any enum to regenerate TypeScript files.

### Frontend Rules

12. **NEVER manually create TypeScript enum files**. They are auto-generated by default in `resources/js/enums/` (configurable via `enumify.paths.output`) and will be overwritten.

13. **ALWAYS import from the barrel file** when `generate_index_barrel` is enabled (default). By default this is `@/enums` (maps to `resources/js/enums/index.ts`, adjustable via `enumify.paths.output`):

    ```typescript
    // CORRECT
    import { OrderStatus, OrderStatusUtils } from "@/enums";

    // WRONG — bypasses barrel file, fragile path
    import { OrderStatus } from "@/enums/order-status";
    ```

14. **ALWAYS use the const object for comparisons**, never raw string values:

    ```typescript
    // CORRECT
    if (order.status === OrderStatus.PENDING) { ... }

    // WRONG — hardcoded string, no type safety
    if (order.status === 'pending') { ... }
    ```

15. **ALWAYS use `Utils` methods** for display values (labels, colors, badges). Never duplicate these mappings in frontend code:

    ```typescript
    // CORRECT
    const label = OrderStatusUtils.label(order.status);
    const color = OrderStatusUtils.color(order.status);
    const allOptions = OrderStatusUtils.options();

    // WRONG — duplicates backend logic, goes out of sync
    const label = order.status === "pending" ? "Pending" : "Shipped";
    ```

16. **When localization is enabled** (`react` or `vue` mode), ALWAYS use the hook function instead of the static Utils:

    ```typescript
    // With localization.mode = 'react'
    const { label, color, options } = useOrderStatusUtils();

    // With localization.mode = 'vue'
    const { label, color, options } = useOrderStatusUtils();
    ```

17. **ALWAYS use `options()` to populate `<select>` dropdowns and filter lists**, never manually define option arrays:

    ```tsx
    // CORRECT — React example
    <select>
        {OrderStatusUtils.options().map(status => (
            <option key={status} value={status}>
                {OrderStatusUtils.label(status)}
            </option>
        ))}
    </select>

    // CORRECT — Vue example
    <select>
        <option v-for="status in OrderStatusUtils.options()" :key="status" :value="status">
            {{ OrderStatusUtils.label(status) }}
        </option>
    </select>
    ```

## Complete Backend Implementation Pattern

When creating a new feature that uses an enum, follow this exact sequence:

### Step 1: Create the enum

```php
// app/Enums/TaskPriority.php
namespace App\Enums;

use DevWizardHQ\Enumify\Concerns\EnumHelpers;
use DevWizardHQ\Enumify\Contracts\HasLabels;

enum TaskPriority: string implements HasLabels
{
    use EnumHelpers;

    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::URGENT => 'Urgent',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'blue',
            self::HIGH => 'orange',
            self::URGENT => 'red',
        };
    }

    public function isEscalated(): bool
    {
        return in_array($this, [self::HIGH, self::URGENT]);
    }
}
```

### Step 2: Cast in the model

```php
// app/Models/Task.php
protected function casts(): array
{
    return [
        'priority' => TaskPriority::class,
    ];
}
```

### Step 3: Use enum references everywhere in backend

```php
// Migration
$table->string('priority')->default(TaskPriority::LOW->value);

// Controller
$request->validate([
    'priority' => ['required', Rule::enum(TaskPriority::class)],
]);

// Query scopes
Task::where('priority', TaskPriority::URGENT)->get();

// Blade
<span class="text-{{ $task->priority->color() }}">
    {{ $task->priority->label() }}
</span>
```

### Step 4: Sync to TypeScript

```bash
php artisan enumify:sync
```

### Step 5: Use in frontend

```typescript
import { TaskPriority, TaskPriorityUtils } from "@/enums";

// Display
const label = TaskPriorityUtils.label(task.priority);
const color = TaskPriorityUtils.color(task.priority);

// Filter
const urgentTasks = tasks.filter((t) => t.priority === TaskPriority.URGENT);

// Select dropdown
TaskPriorityUtils.options().map((p) => ({
    value: p,
    label: TaskPriorityUtils.label(p),
}));
```

## Configuration Reference

```php
// config/enumify.php
return [
    'paths' => [
        'enums'  => ['app/Enums'],        // Directories scanned for PHP enums
        'models' => ['app/Models'],        // Directories scanned for model casts (refactor command)
        'output' => 'resources/js/enums',  // TypeScript output directory
    ],
    'naming' => [
        'file_case' => 'kebab', // 'kebab' | 'camel' | 'pascal' | 'snake'
    ],
    'features' => [
        'generate_union_types'  => true,
        'generate_label_maps'   => true,
        'generate_method_maps'  => true,
        'generate_index_barrel' => true,
    ],
    'localization' => [
        'mode' => 'none', // 'none' | 'react' | 'vue'
    ],
    'filters' => [
        'include' => [],
        'exclude' => [],
    ],
];
```

## Artisan Commands

```bash
php artisan enumify:sync                              # Sync all enums to TypeScript
php artisan enumify:sync --force                      # Force regenerate all files
php artisan enumify:sync --dry-run                    # Preview without writing
php artisan enumify:sync --only="App\Enums\TaskPriority" # Sync a single enum
php artisan enumify:refactor --dry-run                # Scan for hardcoded enum strings
php artisan enumify:refactor --fix                    # Auto-fix hardcoded strings
php artisan enumify:refactor --fix --backup           # Fix with backup
php artisan enumify:refactor --normalize-keys         # Normalize case names to UPPERCASE
```

## Generated TypeScript Structure

Each enum generates a file with three exports — the const object, the union type, and the Utils object:

```typescript
// AUTO-GENERATED — DO NOT EDIT MANUALLY
export const TaskPriority = {
  LOW: "low",
  MEDIUM: "medium",
  HIGH: "high",
  URGENT: "urgent",
} as const;

export type TaskPriority = typeof TaskPriority[keyof typeof TaskPriority];

export const TaskPriorityUtils = {
  label(priority: TaskPriority): string { ... },
  color(priority: TaskPriority): string { ... },
  isEscalated(priority: TaskPriority): boolean { ... },
  options(): TaskPriority[] { return Object.values(TaskPriority); },
};
```

## Common Anti-Patterns to Avoid

| Anti-Pattern                                                        | Correct Pattern                                  |
| ------------------------------------------------------------------- | ------------------------------------------------ |
| `->where('status', 'pending')`                                      | `->where('status', OrderStatus::PENDING)`        |
| `'status' => 'active'` in factories/seeders                         | `'status' => UserStatus::ACTIVE`                 |
| `Rule::in(['low', 'medium', 'high'])`                               | `Rule::enum(TaskPriority::class)`                |
| `if ($status === 'pending')` in TS/JS                               | `if (status === OrderStatus.PENDING)`            |
| Manually typing label maps in frontend                              | Use `OrderStatusUtils.label(status)`             |
| Defining `['Pending', 'Active']` option arrays                      | Use `OrderStatusUtils.options()`                 |
| Creating `.ts` files in `resources/js/enums/` manually              | Run `php artisan enumify:sync`                   |
| `foreach (Enum::cases() as $c) { $opts[$c->value] = $c->label(); }` | `Enum::options()` via `EnumHelpers` trait        |
| `array_column(Enum::cases(), 'value')`                              | `Enum::values()` via `EnumHelpers` trait         |
| Manual `in_array` check against enum values                         | `Enum::hasValue($value)` via `EnumHelpers` trait |
