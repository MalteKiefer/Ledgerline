<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Payments;

use App\Modules\Finance\Domain\Payments\AllocationLedger;
use App\Modules\Finance\Domain\Payments\Exception\InvalidAllocation;
use App\Modules\Finance\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

final class AllocationLedgerTest extends TestCase
{
    public function test_it_applies_positive_allocations_and_keeps_the_original_immutable(): void
    {
        $empty = AllocationLedger::forPayment(Money::fromMinor(15_000, 'EUR'));
        $ledger = $empty->apply(invoiceId: 10, amount: Money::fromMinor(11_900, 'EUR'));

        $this->assertSame(15_000, $empty->remaining()->minor());
        $this->assertSame(3_100, $ledger->remaining()->minor());
        $this->assertSame('EUR', $ledger->remaining()->currency());
    }

    public function test_it_applies_negative_refund_allocations_to_negative_credit_documents(): void
    {
        $refund = AllocationLedger::forPayment(Money::fromMinor(-11_900, 'EUR'))
            ->apply(invoiceId: 11, amount: Money::fromMinor(-11_900, 'EUR'));

        $this->assertSame(0, $refund->remaining()->minor());
    }

    public function test_it_rejects_a_zero_allocation(): void
    {
        $this->assertInvalidCode(
            'allocation_amount_zero',
            fn () => AllocationLedger::forPayment(Money::fromMinor(100, 'EUR'))
                ->apply(10, Money::fromMinor(0, 'EUR')),
        );
    }

    public function test_it_rejects_a_currency_mismatch(): void
    {
        $this->assertInvalidCode(
            'allocation_currency_mismatch',
            fn () => AllocationLedger::forPayment(Money::fromMinor(100, 'EUR'))
                ->apply(10, Money::fromMinor(100, 'USD')),
        );
    }

    public function test_it_rejects_an_allocation_with_the_opposite_sign(): void
    {
        $this->assertInvalidCode(
            'allocation_sign_mismatch',
            fn () => AllocationLedger::forPayment(Money::fromMinor(100, 'EUR'))
                ->apply(10, Money::fromMinor(-1, 'EUR')),
        );
    }

    public function test_it_rejects_allocations_beyond_the_payment_magnitude(): void
    {
        $this->assertInvalidCode(
            'allocation_exceeds_payment',
            fn () => AllocationLedger::forPayment(Money::fromMinor(100, 'EUR'))
                ->apply(10, Money::fromMinor(101, 'EUR')),
        );

        $this->assertInvalidCode(
            'allocation_exceeds_payment',
            fn () => AllocationLedger::forPayment(Money::fromMinor(-100, 'EUR'))
                ->apply(10, Money::fromMinor(-101, 'EUR')),
        );
    }

    public function test_reversal_appends_the_exact_negation_and_cannot_be_repeated(): void
    {
        $allocated = AllocationLedger::forPayment(Money::fromMinor(11_900, 'EUR'))
            ->apply(invoiceId: 10, amount: Money::fromMinor(11_900, 'EUR'), allocationId: 77);
        $reversed = $allocated->reverse(77);

        $this->assertSame(0, $allocated->remaining()->minor());
        $this->assertSame(11_900, $reversed->remaining()->minor());

        $this->assertInvalidCode(
            'allocation_already_reversed',
            fn () => $reversed->reverse(77),
        );
    }

    private function assertInvalidCode(string $expectedCode, callable $operation): void
    {
        try {
            $operation();
            $this->fail('The invalid allocation must be rejected.');
        } catch (InvalidAllocation $exception) {
            $this->assertSame($expectedCode, $exception->getCode());
            $this->assertSame($expectedCode, $exception->errorCode);
        }
    }
}
