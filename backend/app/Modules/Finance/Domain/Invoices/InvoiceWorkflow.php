<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Invoices;

use App\Modules\Finance\Domain\Invoices\Exception\InvalidInvoiceState;
use App\Modules\Finance\Domain\Shared\Workflow\Exception\InvalidTransition;
use App\Modules\Finance\Domain\Shared\Workflow\StateMachine;

final readonly class InvoiceWorkflow
{
    private StateMachine $stateMachine;

    public function __construct()
    {
        $this->stateMachine = new StateMachine([
            InvoiceStatus::Draft->value => [InvoiceStatus::Finalized->value],
            InvoiceStatus::Finalized->value => [InvoiceStatus::Sent->value, InvoiceStatus::Cancelled->value],
            InvoiceStatus::Sent->value => [InvoiceStatus::Cancelled->value],
            InvoiceStatus::PartiallyPaid->value => [InvoiceStatus::Cancelled->value],
            InvoiceStatus::Paid->value => [InvoiceStatus::Cancelled->value],
        ]);
    }

    public function assertCanUpdate(InvoiceStatus $status): void
    {
        if ($status !== InvoiceStatus::Draft) {
            throw new InvalidInvoiceState('invoice_not_editable', $status, 'update');
        }
    }

    public function assertCanFinalize(InvoiceStatus $status): void
    {
        $this->assertCanTransition($status, InvoiceStatus::Finalized);
    }

    public function assertCanSend(InvoiceStatus $status): void
    {
        $this->assertCanTransition($status, InvoiceStatus::Sent);
    }

    public function assertCanCancel(
        InvoiceStatus $status,
        InvoiceKind $kind = InvoiceKind::Invoice,
    ): void {
        if ($kind === InvoiceKind::CreditNote) {
            throw new InvalidInvoiceState('credit_note_cannot_be_cancelled', $status, 'cancel');
        }

        $this->assertCanTransition($status, InvoiceStatus::Cancelled);
    }

    public function assertCanTransition(InvoiceStatus $from, InvoiceStatus $to): void
    {
        try {
            $this->stateMachine->assertCan($from->value, $to->value);
        } catch (InvalidTransition) {
            throw new InvalidInvoiceState('invalid_invoice_transition', $from, $to->value);
        }
    }
}
