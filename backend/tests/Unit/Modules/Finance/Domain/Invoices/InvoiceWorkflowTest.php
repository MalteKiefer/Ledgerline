<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Invoices;

use App\Modules\Finance\Domain\Invoices\Exception\InvalidInvoiceState;
use App\Modules\Finance\Domain\Invoices\InvoiceBalance;
use App\Modules\Finance\Domain\Invoices\InvoiceKind;
use App\Modules\Finance\Domain\Invoices\InvoiceStatus;
use App\Modules\Finance\Domain\Invoices\InvoiceWorkflow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceWorkflowTest extends TestCase
{
    public function test_named_commands_allow_only_their_valid_source_states(): void
    {
        $workflow = new InvoiceWorkflow;

        $workflow->assertCanFinalize(InvoiceStatus::Draft);
        $workflow->assertCanSend(InvoiceStatus::Finalized);
        $workflow->assertCanCancel(InvoiceStatus::Finalized);
        $workflow->assertCanCancel(InvoiceStatus::Sent);
        $workflow->assertCanCancel(InvoiceStatus::PartiallyPaid);
        $workflow->assertCanCancel(InvoiceStatus::Paid);

        $this->addToAssertionCount(6);
    }

    public function test_only_a_draft_can_be_updated(): void
    {
        $workflow = new InvoiceWorkflow;
        $workflow->assertCanUpdate(InvoiceStatus::Draft);

        try {
            $workflow->assertCanUpdate(InvoiceStatus::Finalized);
            $this->fail('A finalized invoice must not be updated.');
        } catch (InvalidInvoiceState $exception) {
            $this->assertSame('invoice_not_editable', $exception->getCode());
            $this->assertSame('invoice_not_editable', $exception->errorCode);
            $this->assertSame(InvoiceStatus::Finalized, $exception->status);
            $this->assertSame('update', $exception->action);
        }
    }

    #[DataProvider('invalidTransitions')]
    public function test_direct_derived_self_and_reverse_transitions_are_rejected(
        InvoiceStatus $from,
        InvoiceStatus $to,
    ): void {
        try {
            (new InvoiceWorkflow)->assertCanTransition($from, $to);
            $this->fail('The transition must be rejected.');
        } catch (InvalidInvoiceState $exception) {
            $this->assertSame('invalid_invoice_transition', $exception->getCode());
            $this->assertSame($from, $exception->status);
            $this->assertSame($to->value, $exception->action);
        }
    }

    /** @return iterable<string, array{InvoiceStatus, InvoiceStatus}> */
    public static function invalidTransitions(): iterable
    {
        yield 'direct settlement projection' => [InvoiceStatus::Sent, InvoiceStatus::Paid];
        yield 'self transition' => [InvoiceStatus::Finalized, InvoiceStatus::Finalized];
        yield 'reverse transition' => [InvoiceStatus::Sent, InvoiceStatus::Finalized];
    }

    public function test_a_credit_note_cannot_be_cancelled(): void
    {
        try {
            (new InvoiceWorkflow)->assertCanCancel(InvoiceStatus::Paid, InvoiceKind::CreditNote);
            $this->fail('A credit note must not be cancelled.');
        } catch (InvalidInvoiceState $exception) {
            $this->assertSame('credit_note_cannot_be_cancelled', $exception->getCode());
            $this->assertSame(InvoiceStatus::Paid, $exception->status);
            $this->assertSame('cancel', $exception->action);
        }
    }

    public function test_balance_projects_partial_payment_and_exact_open_minor_units(): void
    {
        $balance = new InvoiceBalance(
            grossMinor: 11_900,
            allocatedMinor: 5_000,
            wasSent: true,
            cancelled: false,
        );

        $this->assertSame(6_900, $balance->openMinor());
        $this->assertSame(InvoiceStatus::PartiallyPaid, $balance->effectiveStatus());
    }

    public function test_balance_status_precedence_is_cancelled_then_settlement_then_workflow(): void
    {
        $this->assertSame(
            InvoiceStatus::Cancelled,
            (new InvoiceBalance(11_900, 11_900, true, true))->effectiveStatus(),
        );
        $this->assertSame(
            InvoiceStatus::Paid,
            (new InvoiceBalance(11_900, 11_900, false, false))->effectiveStatus(),
        );
        $this->assertSame(
            InvoiceStatus::Sent,
            (new InvoiceBalance(11_900, 0, true, false))->effectiveStatus(),
        );
        $this->assertSame(
            InvoiceStatus::Finalized,
            (new InvoiceBalance(11_900, 0, false, false))->effectiveStatus(),
        );
    }

    public function test_negative_credit_balance_uses_the_same_signed_ledger_invariant(): void
    {
        $balance = new InvoiceBalance(-11_900, -5_000, true, false);

        $this->assertSame(-6_900, $balance->openMinor());
        $this->assertSame(InvoiceStatus::PartiallyPaid, $balance->effectiveStatus());
        $this->assertSame(
            InvoiceStatus::Paid,
            (new InvoiceBalance(-11_900, -11_900, true, false))->effectiveStatus(),
        );
    }

    public function test_negative_open_balance_requires_explicit_overpayment_allowance(): void
    {
        try {
            new InvoiceBalance(11_900, 15_000, true, false);
            $this->fail('An invoice must not be overallocated implicitly.');
        } catch (InvalidInvoiceState $exception) {
            $this->assertSame('invoice_overallocated', $exception->getCode());
        }

        $allowed = new InvoiceBalance(11_900, 15_000, true, false, allowOverpayment: true);

        $this->assertSame(-3_100, $allowed->openMinor());
        $this->assertSame(InvoiceStatus::Paid, $allowed->effectiveStatus());
    }

    public function test_allocated_minor_units_must_have_the_invoice_sign(): void
    {
        try {
            new InvoiceBalance(-11_900, 1, true, false);
            $this->fail('A credit note must not receive a positive allocation.');
        } catch (InvalidInvoiceState $exception) {
            $this->assertSame('invoice_allocation_sign_mismatch', $exception->getCode());
        }
    }
}
