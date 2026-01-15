<?php

declare(strict_types=1);

namespace DevWizardHQ\Enumify\Tests\Fixtures;

use DevWizardHQ\Enumify\Contracts\HasLabels;

/**
 * Fixture: Backed enum with per-case label() method.
 */
enum PaymentMethod: string implements HasLabels
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case BANK_TRANSFER = 'bank_transfer';
    case PAYPAL = 'paypal';
    case CRYPTO = 'crypto';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::PAYPAL => 'PayPal',
            self::CRYPTO => 'Cryptocurrency',
        };
    }
}
