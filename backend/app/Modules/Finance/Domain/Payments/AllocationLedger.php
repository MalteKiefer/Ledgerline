<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Payments;

use App\Modules\Finance\Domain\Payments\Exception\InvalidAllocation;
use App\Modules\Finance\Domain\Shared\Money;

final readonly class AllocationLedger
{
    /**
     * @param  array<int, array{invoiceId: int, amount: Money, reversesAllocationId: ?int}>  $entries
     * @param  array<int, true>  $reversedAllocationIds
     */
    private function __construct(
        private Money $payment,
        private array $entries,
        private array $reversedAllocationIds,
        private int $nextAllocationId,
    ) {}

    public static function forPayment(Money $payment): self
    {
        if ($payment->minor() === 0) {
            throw new InvalidAllocation('payment_amount_zero', 'A payment amount must not be zero.');
        }

        return new self($payment, [], [], 1);
    }

    public function apply(int $invoiceId, Money $amount, ?int $allocationId = null): self
    {
        if ($invoiceId <= 0) {
            throw new InvalidAllocation('invalid_invoice_id', 'An allocation requires a positive invoice ID.');
        }

        $this->assertCurrency($amount);

        if ($amount->minor() === 0) {
            throw new InvalidAllocation('allocation_amount_zero', 'An allocation amount must not be zero.');
        }

        if (($amount->minor() <=> 0) !== ($this->payment->minor() <=> 0)) {
            throw new InvalidAllocation(
                'allocation_sign_mismatch',
                'An allocation must have the same sign as its payment.',
            );
        }

        $remaining = $this->remaining()->subtract($amount);

        if ($remaining->minor() !== 0
            && ($remaining->minor() <=> 0) !== ($this->payment->minor() <=> 0)) {
            throw new InvalidAllocation(
                'allocation_exceeds_payment',
                'Allocations must not exceed the payment magnitude.',
            );
        }

        [$id, $nextAllocationId] = $this->reserveAllocationId($allocationId);

        $entries = $this->entries;
        $entries[$id] = [
            'invoiceId' => $invoiceId,
            'amount' => $amount,
            'reversesAllocationId' => null,
        ];

        return new self(
            $this->payment,
            $entries,
            $this->reversedAllocationIds,
            $nextAllocationId,
        );
    }

    public function reverse(int $allocationId): self
    {
        if (! isset($this->entries[$allocationId])) {
            throw new InvalidAllocation('allocation_not_found', 'The allocation to reverse does not exist.');
        }

        if ($this->entries[$allocationId]['reversesAllocationId'] !== null) {
            throw new InvalidAllocation(
                'allocation_reversal_not_reversible',
                'A reversal entry cannot itself be reversed.',
            );
        }

        if (isset($this->reversedAllocationIds[$allocationId])) {
            throw new InvalidAllocation(
                'allocation_already_reversed',
                'An allocation can be reversed only once.',
            );
        }

        $original = $this->entries[$allocationId];
        [$reversalId, $nextAllocationId] = $this->reserveAllocationId(null);
        $reversalAmount = Money::fromMinor(-$original['amount']->minor(), $original['amount']->currency());
        $entries = $this->entries;
        $entries[$reversalId] = [
            'invoiceId' => $original['invoiceId'],
            'amount' => $reversalAmount,
            'reversesAllocationId' => $allocationId,
        ];
        $reversedAllocationIds = $this->reversedAllocationIds;
        $reversedAllocationIds[$allocationId] = true;

        return new self(
            $this->payment,
            $entries,
            $reversedAllocationIds,
            $nextAllocationId,
        );
    }

    public function remaining(): Money
    {
        $allocated = Money::fromMinor(0, $this->payment->currency());

        foreach ($this->entries as $entry) {
            $allocated = $allocated->add($entry['amount']);
        }

        return $this->payment->subtract($allocated);
    }

    private function assertCurrency(Money $amount): void
    {
        if ($amount->currency() !== $this->payment->currency()) {
            throw new InvalidAllocation(
                'allocation_currency_mismatch',
                'An allocation must use the payment currency.',
            );
        }
    }

    /** @return array{int, int} */
    private function reserveAllocationId(?int $requestedAllocationId): array
    {
        $allocationId = $requestedAllocationId ?? $this->nextAllocationId;

        if ($allocationId <= 0) {
            throw new InvalidAllocation('invalid_allocation_id', 'An allocation requires a positive usable ID.');
        }

        if (isset($this->entries[$allocationId])) {
            throw new InvalidAllocation('duplicate_allocation_id', 'Allocation IDs must be unique.');
        }

        if ($allocationId === PHP_INT_MAX) {
            throw new InvalidAllocation(
                'allocation_id_exhausted',
                'No unique follow-up allocation ID remains.',
            );
        }

        return [
            $allocationId,
            max($this->nextAllocationId, $allocationId + 1),
        ];
    }
}
