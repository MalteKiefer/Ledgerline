<x-mail::message>
# {{ $invoice->type->label() }} {{ $invoice->number }}

@if ($invoice->customer)
Dear {{ $invoice->customer->name }},
@endif

Please find {{ strtolower($invoice->type->label()) }} **{{ $invoice->number }}** attached, dated {{ $invoice->issue_date?->format('Y-m-d') }}.

**{{ $invoice->gross()->format() }}**@if ($invoice->due_date) is payable by {{ $invoice->due_date->format('Y-m-d') }}@endif.

Thank you.
</x-mail::message>
