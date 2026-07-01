<!DOCTYPE html>
<html lang="{{ $invoice->language }}">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 12px; margin: 0; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .header { width: 100%; }
        .header td { vertical-align: top; }
        .parties { width: 100%; margin-top: 28px; }
        .parties td { vertical-align: top; width: 50%; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-bottom: 4px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 28px; }
        table.lines th { text-align: left; border-bottom: 1px solid #d1d5db; padding: 6px 4px; font-size: 10px; text-transform: uppercase; color: #6b7280; }
        table.lines td { padding: 6px 4px; border-bottom: 1px solid #f3f4f6; }
        .totals { width: 45%; margin-left: 55%; margin-top: 14px; border-collapse: collapse; }
        .totals td { padding: 4px 4px; }
        .totals .grand td { border-top: 1px solid #d1d5db; font-weight: bold; font-size: 14px; }
        .note { margin-top: 20px; }
        .foot { margin-top: 28px; font-size: 11px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <strong>{{ $company->legal_name }}</strong><br>
                @if ($company->address_line1)<span class="muted">{{ $company->address_line1 }}</span><br>@endif
                <span class="muted">{{ $company->postal_code }} {{ $company->city }}</span>
            </td>
            <td class="right">
                <h1>{{ $invoice->type->value === 'CREDIT_NOTE' ? __('invoice.credit_note') : __('invoice.invoice') }}</h1>
                <span class="muted">{{ __('invoice.number') }}: {{ $invoice->number ?? '—' }}</span><br>
                <span class="muted">{{ __('invoice.issue_date') }}: {{ $invoice->issue_date?->format('Y-m-d') }}</span><br>
                @if ($invoice->due_date)<span class="muted">{{ __('invoice.due_date') }}: {{ $invoice->due_date->format('Y-m-d') }}</span>@endif
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">{{ __('invoice.from') }}</div>
                {{ $company->legal_name }}<br>
                @if ($company->address_line1){{ $company->address_line1 }}<br>@endif
                {{ $company->postal_code }} {{ $company->city }}<br>
                @if ($company->vat_id){{ __('invoice.vat_id') }}: {{ $company->vat_id }}<br>@endif
                @if ($company->tax_number){{ __('invoice.tax_number') }}: {{ $company->tax_number }}@endif
            </td>
            <td>
                <div class="label">{{ __('invoice.to') }}</div>
                {{ $invoice->customer?->name }}<br>
                @if ($invoice->customer?->street){{ $invoice->customer->street }}<br>@endif
                {{ $invoice->customer?->postal_code }} {{ $invoice->customer?->city }}<br>
                @if ($invoice->customer?->vat_id){{ __('invoice.vat_id') }}: {{ $invoice->customer->vat_id }}@endif
            </td>
        </tr>
    </table>

    @if ($invoice->intro_text)<p class="note">{{ $invoice->intro_text }}</p>@endif

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('invoice.pos') }}</th>
                <th>{{ __('invoice.description') }}</th>
                <th class="right">{{ __('invoice.quantity') }}</th>
                <th class="right">{{ __('invoice.unit_price') }}</th>
                <th class="right">{{ __('invoice.vat') }}</th>
                <th class="right">{{ __('invoice.line_net') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }} {{ $line->unit }}</td>
                    <td class="right">{{ $line->unitPrice()->format() }}</td>
                    <td class="right">{{ $invoice->tax_mode->chargesTax() ? $line->tax_rate.'%' : '—' }}</td>
                    <td class="right">{{ $line->lineNet()->format() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($invoice->discount_cents > 0)
            <tr><td class="muted">{{ __('invoice.discount') }}</td><td class="right">-{{ number_format($invoice->discount_cents / 100, 2) }} {{ $invoice->currency }}</td></tr>
        @endif
        <tr><td class="muted">{{ __('invoice.net') }}</td><td class="right">{{ $invoice->net()->format() }}</td></tr>
        <tr><td class="muted">{{ __('invoice.tax') }}</td><td class="right">{{ $invoice->tax()->format() }}</td></tr>
        <tr class="grand"><td>{{ __('invoice.gross') }}</td><td class="right">{{ $invoice->gross()->format() }}</td></tr>
    </table>

    @unless ($invoice->tax_mode->chargesTax())
        <p class="note muted">
            {{ $invoice->tax_mode->value === 'REVERSE_CHARGE' ? __('invoice.reverse_charge_note') : __('invoice.small_business_note') }}
        </p>
    @endunless

    @if ($invoice->payment_terms_days)
        <p class="note">{{ __('invoice.payment_terms') }}: {{ __('invoice.payment_terms_days', ['days' => $invoice->payment_terms_days]) }}</p>
    @endif

    @if ($invoice->closing_text)<p class="note">{{ $invoice->closing_text }}</p>@endif

    <div class="foot muted">
        @if ($company->iban)
            <strong>{{ __('invoice.bank_details') }}:</strong>
            {{ __('invoice.iban') }} {{ $company->iban }}@if ($company->bic) · {{ __('invoice.bic') }} {{ $company->bic }}@endif
        @endif
        @if ($company->invoice_footer_text)<br>{{ $company->invoice_footer_text }}@endif
    </div>
</body>
</html>
