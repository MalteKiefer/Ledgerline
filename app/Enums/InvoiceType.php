<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a document is a normal invoice or a credit note (Gutschrift/Storno).
 */
enum InvoiceType: string
{
    case INVOICE = 'INVOICE';
    case CREDIT_NOTE = 'CREDIT_NOTE';

    public function label(): string
    {
        return __('enums.invoice_type.'.$this->name);
    }
}
