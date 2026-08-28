<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Invoices;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
