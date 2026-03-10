<?php

declare(strict_types=1);

use DevWizardHQ\Enumify\Tests\Fixtures\CampusStatus;
use DevWizardHQ\Enumify\Tests\Fixtures\PaymentMethod;

it('returns options as value => label pairs', function () {
    $options = PaymentMethod::options();

    expect($options)->toBe([
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'bank_transfer' => 'Bank Transfer',
        'paypal' => 'PayPal',
        'crypto' => 'Cryptocurrency',
    ]);
});

it('returns options using a custom method name', function () {
    $options = CampusStatus::options('color');

    expect($options)->toBe([
        'active' => 'green',
        'suspended' => 'red',
        'inactive' => 'gray',
    ]);
});

it('returns select options as value/label arrays', function () {
    $options = PaymentMethod::selectOptions();

    expect($options)->toBe([
        ['value' => 'credit_card', 'label' => 'Credit Card'],
        ['value' => 'debit_card', 'label' => 'Debit Card'],
        ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
        ['value' => 'paypal', 'label' => 'PayPal'],
        ['value' => 'crypto', 'label' => 'Cryptocurrency'],
    ]);
});

it('returns select options using a custom method name', function () {
    $options = CampusStatus::selectOptions('color');

    expect($options)->toBe([
        ['value' => 'active', 'label' => 'green'],
        ['value' => 'suspended', 'label' => 'red'],
        ['value' => 'inactive', 'label' => 'gray'],
    ]);
});

it('returns all enum values', function () {
    expect(PaymentMethod::values())->toBe([
        'credit_card',
        'debit_card',
        'bank_transfer',
        'paypal',
        'crypto',
    ]);
});

it('returns all enum names', function () {
    expect(PaymentMethod::names())->toBe([
        'CREDIT_CARD',
        'DEBIT_CARD',
        'BANK_TRANSFER',
        'PAYPAL',
        'CRYPTO',
    ]);
});

it('checks if a value exists', function () {
    expect(PaymentMethod::hasValue('credit_card'))->toBeTrue();
    expect(PaymentMethod::hasValue('nonexistent'))->toBeFalse();
});

it('uses strict comparison for hasValue', function () {
    expect(PaymentMethod::hasValue(''))->toBeFalse();
});
