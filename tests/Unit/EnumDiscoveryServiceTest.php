<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Data\EnumCaseDefinition;
use DevWizardHQ\Enumify\Data\EnumDefinition;
use DevWizardHQ\Enumify\Data\EnumMethodDefinition;
use DevWizardHQ\Enumify\Services\EnumDiscoveryService;

beforeEach(function () {
    $this->discovery = new EnumDiscoveryService;
    $this->fixturesPath = __DIR__.'/../Fixtures';

    // Ensure all fixture enums are loaded
    require_once $this->fixturesPath.'/OrderStatus.php';
    require_once $this->fixturesPath.'/Priority.php';
    require_once $this->fixturesPath.'/PaymentMethod.php';
    require_once $this->fixturesPath.'/UserRole.php';
    require_once $this->fixturesPath.'/HttpStatus.php';
    require_once $this->fixturesPath.'/CampusStatus.php';
    require_once $this->fixturesPath.'/LabelsWithEnumKeys.php';
    require_once $this->fixturesPath.'/LabelsThrowing.php';
    require_once $this->fixturesPath.'/NonStaticLabels.php';
    require_once $this->fixturesPath.'/LabelThrows.php';
    require_once $this->fixturesPath.'/LabelNonString.php';
    require_once $this->fixturesPath.'/LabelsNonArray.php';
    require_once $this->fixturesPath.'/MethodEdgeCases.php';
});

describe('EnumDiscoveryService', function () {
    it('discovers backed enums with string values', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $orderStatus = collect($enums)->firstWhere('name', 'OrderStatus');

        expect($orderStatus)
            ->toBeInstanceOf(EnumDefinition::class)
            ->and($orderStatus->isBacked)
            ->toBeTrue()
            ->and($orderStatus->backingType)
            ->toBe('string')
            ->and($orderStatus->cases)
            ->toHaveCount(5);
    });

    it('discovers backed enums with integer values', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $httpStatus = collect($enums)->firstWhere('name', 'HttpStatus');

        expect($httpStatus)
            ->toBeInstanceOf(EnumDefinition::class)
            ->and($httpStatus->isBacked)
            ->toBeTrue()
            ->and($httpStatus->backingType)
            ->toBe('int')
            ->and($httpStatus->cases)
            ->toHaveCount(6);

        // Check specific case value
        $okCase = collect($httpStatus->cases)->firstWhere('name', 'OK');
        expect($okCase->value)->toBe(200);
    });

    it('discovers unit enums', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $priority = collect($enums)->firstWhere('name', 'Priority');

        expect($priority)
            ->toBeInstanceOf(EnumDefinition::class)
            ->and($priority->isBacked)
            ->toBeFalse()
            ->and($priority->backingType)
            ->toBeNull()
            ->and($priority->cases)
            ->toHaveCount(4);
    });

    it('extracts labels from label() method', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $paymentMethod = collect($enums)->firstWhere('name', 'PaymentMethod');

        expect($paymentMethod)->toBeInstanceOf(EnumDefinition::class);

        $creditCard = collect($paymentMethod->cases)->firstWhere('name', 'CREDIT_CARD');
        expect($creditCard->label)->toBe('Credit Card');

        $paypal = collect($paymentMethod->cases)->firstWhere('name', 'PAYPAL');
        expect($paypal->label)->toBe('PayPal');
    });

    it('extracts labels from static labels() method', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $userRole = collect($enums)->firstWhere('name', 'UserRole');

        expect($userRole)->toBeInstanceOf(EnumDefinition::class);

        $admin = collect($userRole->cases)->firstWhere('name', 'ADMIN');
        expect($admin->label)->toBe('Administrator');

        $editor = collect($userRole->cases)->firstWhere('name', 'EDITOR');
        expect($editor->label)->toBe('Content Editor');
    });

    it('discovers custom methods on enums', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $campusStatus = collect($enums)->firstWhere('name', 'CampusStatus');

        expect($campusStatus)
            ->toBeInstanceOf(EnumDefinition::class)
            ->and($campusStatus->methods)
            ->toBeArray()
            ->and($campusStatus->methods)
            ->not
            ->toBeEmpty();

        // Check that color method is discovered
        $colorMethod = collect($campusStatus->methods)->firstWhere('name', 'color');
        expect($colorMethod)
            ->toBeInstanceOf(EnumMethodDefinition::class)
            ->and($colorMethod->returnTypes)
            ->toBe(['string'])
            ->and($colorMethod->getTypeScriptType())
            ->toBe('string')
            ->and($colorMethod->values['ACTIVE'])
            ->toBe('green')
            ->and($colorMethod->values['SUSPENDED'])
            ->toBe('red');
    });

    it('discovers boolean methods on enums', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $campusStatus = collect($enums)->firstWhere('name', 'CampusStatus');

        $isActiveMethod = collect($campusStatus->methods)->firstWhere('name', 'isActive');
        expect($isActiveMethod)
            ->toBeInstanceOf(EnumMethodDefinition::class)
            ->and($isActiveMethod->returnTypes)
            ->toBe(['bool'])
            ->and($isActiveMethod->getTypeScriptType())
            ->toBe('boolean')
            ->and($isActiveMethod->values['ACTIVE'])
            ->toBeTrue()
            ->and($isActiveMethod->values['SUSPENDED'])
            ->toBeFalse()
            ->and($isActiveMethod->values['INACTIVE'])
            ->toBeFalse();
    });

    it('discovers integer-returning methods on enums', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $campusStatus = collect($enums)->firstWhere('name', 'CampusStatus');

        $priorityMethod = collect($campusStatus->methods)->firstWhere('name', 'priority');
        expect($priorityMethod)
            ->toBeInstanceOf(EnumMethodDefinition::class)
            ->and($priorityMethod->returnTypes)
            ->toBe(['int'])
            ->and($priorityMethod->getTypeScriptType())
            ->toBe('number')
            ->and($priorityMethod->values['ACTIVE'])
            ->toBe(1)
            ->and($priorityMethod->values['INACTIVE'])
            ->toBe(3);
    });

    it('discovers nullable string methods on enums', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $campusStatus = collect($enums)->firstWhere('name', 'CampusStatus');

        $badgeMethod = collect($campusStatus->methods)->firstWhere('name', 'badge');
        expect($badgeMethod)
            ->toBeInstanceOf(EnumMethodDefinition::class)
            ->and($badgeMethod->returnTypes)
            ->toBe(['string', 'null'])
            ->and($badgeMethod->getTypeScriptType())
            ->toBe('string | null')
            ->and($badgeMethod->values['ACTIVE'])
            ->toBe('primary')
            ->and($badgeMethod->values['INACTIVE'])
            ->toBeNull();
    });

    it('applies include filters', function () {
        $enums = $this->discovery->discover(
            [$this->fixturesPath],
            ['DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus'],
            []
        );

        expect($enums)
            ->toHaveCount(1)
            ->and($enums[0]->name)
            ->toBe('OrderStatus');
    });

    it('applies exclude filters', function () {
        $enums = $this->discovery->discover(
            [$this->fixturesPath],
            [],
            ['DevWizardHQ\Enumify\Tests\Fixtures\OrderStatus']
        );

        $names = array_map(fn ($e) => $e->name, $enums);

        expect($names)->not->toContain('OrderStatus');
    });

    it('applies glob patterns for filters', function () {
        $enums = $this->discovery->discover(
            [$this->fixturesPath],
            ['DevWizardHQ\Enumify\Tests\Fixtures\*Status'],
            []
        );

        $names = array_map(fn ($e) => $e->name, $enums);

        expect($names)
            ->toContain('OrderStatus')
            ->and($names)
            ->toContain('CampusStatus')
            ->and($names)
            ->toContain('HttpStatus');
    });

    it('returns enums in deterministic order', function () {
        $enums1 = $this->discovery->discover([$this->fixturesPath], [], []);
        $enums2 = $this->discovery->discover([$this->fixturesPath], [], []);

        $names1 = array_map(fn ($e) => $e->name, $enums1);
        $names2 = array_map(fn ($e) => $e->name, $enums2);

        expect($names1)->toBe($names2);
    });

    it('skips non-directories, non-php files, and invalid enum files', function () {
        $tempDir = sys_get_temp_dir().'/enumify-discovery-'.uniqid();
        mkdir($tempDir, 0755, true);

        file_put_contents($tempDir.'/readme.txt', 'not php');
        file_put_contents($tempDir.'/NoNamespace.php', "<?php\nenum NoNamespace { case ONE; }\n");
        file_put_contents($tempDir.'/NoEnum.php', "<?php\nnamespace DevWizardHQ\\Enumify\\Tests\\Fixtures;\nclass NotEnum {}\n");
        file_put_contents($tempDir.'/ValidEnum.php', "<?php\nnamespace DevWizardHQ\\Enumify\\Tests\\Fixtures;\nenum TempEnum { case ONE; }\n");
        file_put_contents($tempDir.'/UnloadedEnum.php', "<?php\nnamespace DevWizardHQ\\Enumify\\Tests\\Fixtures;\nenum UnloadedEnum { case ONE; }\n");

        require_once $tempDir.'/NoEnum.php';
        require_once $tempDir.'/ValidEnum.php';

        $enums = $this->discovery->discover([$tempDir, $tempDir.'/missing'], [], []);
        $names = array_map(fn ($e) => $e->name, $enums);

        expect($names)->toContain('TempEnum');
    });

    it('supports relative enum paths', function () {
        $originalBasePath = app()->basePath();
        app()->setBasePath(dirname(__DIR__, 2));

        $relativePath = 'tests/Fixtures';
        $enums = $this->discovery->discover([$relativePath], [], []);
        $names = array_map(fn ($e) => $e->name, $enums);

        app()->setBasePath($originalBasePath);

        expect($names)->toContain('OrderStatus');
    });

    it('normalizes static labels keyed by enum cases', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $labelsEnum = collect($enums)->firstWhere('name', 'LabelsWithEnumKeys');
        $primary = collect($labelsEnum->cases)->firstWhere('name', 'PRIMARY');

        expect($primary->label)->toBe('Primary');
    });

    it('ignores non-static labels and handles throwing labels', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $nonStatic = collect($enums)->firstWhere('name', 'NonStaticLabels');
        $labelsThrowing = collect($enums)->firstWhere('name', 'LabelsThrowing');
        $labelsNonArray = collect($enums)->firstWhere('name', 'LabelsNonArray');

        $caseOne = collect($nonStatic->cases)->firstWhere('name', 'ONE');
        $caseBroken = collect($labelsThrowing->cases)->firstWhere('name', 'BROKEN');
        $caseValue = collect($labelsNonArray->cases)->firstWhere('name', 'VALUE');

        expect($caseOne->label)->toBeNull()
            ->and($caseBroken->label)->toBeNull()
            ->and($caseValue->label)->toBeNull();
    });

    it('falls back when per-case label throws or returns non-string', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $labelThrows = collect($enums)->firstWhere('name', 'LabelThrows');
        $labelNonString = collect($enums)->firstWhere('name', 'LabelNonString');

        $throwCase = collect($labelThrows->cases)->firstWhere('name', 'FAIL');
        $valueCase = collect($labelNonString->cases)->firstWhere('name', 'VALUE');

        expect($throwCase->label)->toBeNull()
            ->and($valueCase->label)->toBe('Value');
    });

    it('skips unsupported methods and handles method exceptions', function () {
        $enums = $this->discovery->discover([$this->fixturesPath], [], []);

        $edgeCases = collect($enums)->firstWhere('name', 'MethodEdgeCases');

        $methodNames = array_map(fn (EnumMethodDefinition $method) => $method->name, $edgeCases->methods);

        expect($methodNames)->toBe(['unionNumbers']);
    });

    it('resolves return types for nullable and union methods', function () {
        $resolve = new ReflectionMethod(EnumDiscoveryService::class, 'resolveReturnTypes');
        $resolve->setAccessible(true);

        $nullableString = new ReflectionFunction(fn (): ?string => null);
        $booleanType = new ReflectionFunction(fn (): bool => true);
        $integerType = new ReflectionFunction(fn (): int => 1);
        $unionNumbers = new ReflectionFunction(fn (): int|float => 1);
        $unsupportedUnion = new ReflectionFunction(fn (): string|array => 'x');
        $intersection = new ReflectionFunction(fn (): \ArrayAccess&\Countable => new \ArrayObject);
        $unionWithIntersection = new ReflectionFunction(fn (): (\ArrayAccess&\Countable)|string => 'x');

        expect($resolve->invoke($this->discovery, null))->toBeNull()
            ->and($resolve->invoke($this->discovery, $nullableString->getReturnType()))->toBe(['string', 'null'])
            ->and($resolve->invoke($this->discovery, $booleanType->getReturnType()))->toBe(['bool'])
            ->and($resolve->invoke($this->discovery, $integerType->getReturnType()))->toBe(['int'])
            ->and($resolve->invoke($this->discovery, $unionNumbers->getReturnType()))->toBe(['int', 'float'])
            ->and($resolve->invoke($this->discovery, $unsupportedUnion->getReturnType()))->toBeNull()
            ->and($resolve->invoke($this->discovery, $intersection->getReturnType()))->toBeNull()
            ->and($resolve->invoke($this->discovery, $unionWithIntersection->getReturnType()))->toBeNull();
    });

    it('builds type unions for TypeScript output', function () {
        $resolveType = new ReflectionMethod(EnumDiscoveryService::class, 'resolveTypeScriptType');
        $resolveType->setAccessible(true);

        $type = $resolveType->invoke($this->discovery, ['string', 'int', 'bool', 'null']);

        expect($type)->toBe('string | number | boolean | null');
    });

    it('normalizes scalar type aliases', function () {
        $normalize = new ReflectionMethod(EnumDiscoveryService::class, 'normalizeTypeName');
        $normalize->setAccessible(true);

        expect($normalize->invoke($this->discovery, 'boolean'))->toBe('bool')
            ->and($normalize->invoke($this->discovery, 'integer'))->toBe('int');
    });

    it('returns null when buildDefinition fails', function () {
        $build = new ReflectionMethod(EnumDiscoveryService::class, 'buildDefinition');
        $build->setAccessible(true);

        expect($build->invoke($this->discovery, 'Missing\\Enum'))->toBeNull();
    });

    it('returns false for unknown enums', function () {
        $method = new ReflectionMethod(EnumDiscoveryService::class, 'isEnum');
        $method->setAccessible(true);

        expect($method->invoke($this->discovery, 'Missing\\Enum'))->toBeFalse();
    });
});

describe('EnumCaseDefinition', function () {
    it('preserves original case names', function () {
        $case = new EnumCaseDefinition('PENDING_PAYMENT', 'pending_payment');

        expect($case->getTypeScriptName())->toBe('PENDING_PAYMENT');
    });

    it('handles single word case names', function () {
        $case = new EnumCaseDefinition('ACTIVE', 'active');

        expect($case->getTypeScriptName())->toBe('ACTIVE');
    });

    it('formats string values correctly', function () {
        $case = new EnumCaseDefinition('ACTIVE', 'active');

        expect($case->getTypeScriptValue())->toBe('"active"');
    });

    it('formats integer values correctly', function () {
        $case = new EnumCaseDefinition('OK', 200);

        expect($case->getTypeScriptValue())->toBe('200');
    });

    it('formats unit enum values as case name', function () {
        $case = new EnumCaseDefinition('HIGH', null);

        expect($case->getTypeScriptValue())->toBe('"HIGH"');
    });
});
