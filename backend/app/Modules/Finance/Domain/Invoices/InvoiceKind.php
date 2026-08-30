<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Invoices;

enum InvoiceKind: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
}
