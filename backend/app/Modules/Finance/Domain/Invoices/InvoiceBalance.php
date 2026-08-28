<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Invoices;

use App\Modules\Finance\Domain\Invoices\Exception\InvalidInvoiceState;

final readonly class InvoiceBalance
{
    private int $openMinor;

    public function __construct(
        private int $grossMinor,
        private int $allocatedMinor,
        private bool $wasSent,
        private bool $cancelled,
        bool $allowOverpayment = false,
    ) {
        if ($grossMinor === 0) {
            throw new InvalidInvoiceState('invoice_gross_zero', InvoiceStatus::Finalized, 'calculate_balance');
        }

        if ($allocatedMinor !== 0 && self::sign($allocatedMinor) !== self::sign($grossMinor)) {
            throw new InvalidInvoiceState(
                'invoice_allocation_sign_mismatch',
                InvoiceStatus::Finalized,
                'calculate_balance',
            );
        }

        $this->openMinor = self::checkedSubtract($grossMinor, $allocatedMinor);

        if (! $allowOverpayment
            && $this->openMinor !== 0
            && self::sign($this->openMinor) !== self::sign($grossMinor)) {
            throw new InvalidInvoiceState('invoice_overallocated', InvoiceStatus::Finalized, 'calculate_balance');
        }
    }

    public function openMinor(): int
    {
        return $this->openMinor;
    }

    public function effectiveStatus(): InvoiceStatus
    {
        if ($this->cancelled) {
            return InvoiceStatus::Cancelled;
        }

        if ($this->allocatedMinor === 0) {
            return $this->wasSent ? InvoiceStatus::Sent : InvoiceStatus::Finalized;
        }

        if ($this->openMinor === 0 || self::sign($this->openMinor) !== self::sign($this->grossMinor)) {
            return InvoiceStatus::Paid;
        }

        return InvoiceStatus::PartiallyPaid;
    }

    private static function sign(int $value): int
    {
        return $value <=> 0;
    }

    private static function checkedSubtract(int $left, int $right): int
    {
        if (($right < 0 && $left > PHP_INT_MAX + $right)
            || ($right > 0 && $left < PHP_INT_MIN + $right)) {
            throw new InvalidInvoiceState(
                'invoice_balance_overflow',
                InvoiceStatus::Finalized,
                'calculate_balance',
            );
        }

        return $left - $right;
    }
}
