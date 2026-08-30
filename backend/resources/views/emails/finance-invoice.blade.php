{{ $reminder
    ? __('invoices.invoice_reminder_body', ['number' => $number, 'customer' => $customer, 'days' => $daysOverdue, 'gross' => $openAmount])
    : __('invoices.email_body', ['number' => $number, 'company' => $company]) }}
