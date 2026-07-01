<x-layouts.app :title="$invoice->number ?? 'Draft invoice'">
    @php $isCredit = $invoice->type->value === 'CREDIT_NOTE'; @endphp

    <p class="text-sm text-gray-500"><a href="{{ route('finance.invoices.index') }}" class="hover:underline">Invoices</a></p>

    <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">
                {{ $isCredit ? 'Credit note' : 'Invoice' }} {{ $invoice->number ?? '(draft #'.$invoice->id.')' }}
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                <span @class([
                    'rounded px-2 py-0.5 text-xs',
                    'bg-gray-100 text-gray-700' => $invoice->status->value === 'DRAFT',
                    'bg-blue-100 text-blue-800' => $invoice->status->value === 'SENT',
                    'bg-green-100 text-green-800' => $invoice->status->value === 'PAID',
                    'bg-red-100 text-red-800' => $invoice->status->value === 'OVERDUE',
                    'bg-gray-200 text-gray-600' => $invoice->status->value === 'CANCELLED',
                ])>{{ $invoice->status->label() }}</span>
                @if ($invoice->parent)
                    · credits <a href="{{ route('finance.invoices.show', $invoice->parent) }}" class="hover:underline">{{ $invoice->parent->number ?? 'draft' }}</a>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('finance.invoices.pdf', $invoice) }}" target="_blank" rel="noopener"
                class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">PDF ↗</a>
            @if ($invoice->isDraft())
                <a href="{{ route('finance.invoices.edit', $invoice) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                <form method="POST" action="{{ route('finance.invoices.finalize', $invoice) }}" onsubmit="return confirm('Finalise this invoice? It will be locked and numbered.');">
                    @csrf
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Finalise</button>
                </form>
                <form method="POST" action="{{ route('finance.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this draft?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                </form>
            @elseif (! $isCredit && $invoice->status->value !== 'CANCELLED')
                <form method="POST" action="{{ route('finance.invoices.credit-note', $invoice) }}" onsubmit="return confirm('Create a credit note and cancel this invoice?');">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Create credit note</button>
                </form>
            @endif
        </div>
    </div>

    @if (session('error'))
        <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Parties --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">From</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $company->legal_name }}</p>
                    @if ($company->address_line1)<p class="text-gray-600">{{ $company->address_line1 }}</p>@endif
                    @if ($company->postal_code || $company->city)<p class="text-gray-600">{{ $company->postal_code }} {{ $company->city }}</p>@endif
                    @if ($company->vat_id)<p class="text-gray-500">VAT: {{ $company->vat_id }}</p>@endif
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">To</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $invoice->customer?->name ?? '—' }}</p>
                    @if ($invoice->customer?->street)<p class="text-gray-600">{{ $invoice->customer->street }}</p>@endif
                    @if ($invoice->customer && ($invoice->customer->postal_code || $invoice->customer->city))
                        <p class="text-gray-600">{{ $invoice->customer->postal_code }} {{ $invoice->customer->city }}</p>
                    @endif
                    @if ($invoice->customer?->vat_id)<p class="text-gray-500">VAT: {{ $invoice->customer->vat_id }}</p>@endif
                </div>
            </div>

            {{-- Lines --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Net price</th>
                            <th class="px-4 py-3 text-right">VAT</th>
                            <th class="px-4 py-3 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($invoice->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-gray-900">{{ $line->description }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }} {{ $line->unit }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $line->unitPrice()->format() }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $invoice->tax_mode->chargesTax() ? $line->tax_rate.'%' : '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ $line->lineNet()->format() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No lines.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (! $invoice->tax_mode->chargesTax())
                <p class="text-sm text-gray-600">
                    {{ $invoice->tax_mode->value === 'REVERSE_CHARGE'
                        ? 'Reverse charge: VAT is to be accounted for by the recipient.'
                        : 'Small business under §19 UStG — no VAT is charged.' }}
                </p>
            @endif

            @if ($invoice->intro_text)<p class="text-sm text-gray-700">{{ $invoice->intro_text }}</p>@endif
            @if ($invoice->closing_text)<p class="text-sm text-gray-700">{{ $invoice->closing_text }}</p>@endif
        </div>

        {{-- Totals + payment --}}
        <div class="space-y-6">
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-sm shadow-sm">
                <dl class="space-y-2">
                    @if ($invoice->discount_cents > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd class="text-gray-900">-{{ number_format($invoice->discount_cents / 100, 2) }} {{ $invoice->currency }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Net</dt><dd class="text-gray-900">{{ $invoice->net()->format() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">VAT</dt><dd class="text-gray-900">{{ $invoice->tax()->format() }}</dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-semibold"><dt>Gross</dt><dd>{{ $invoice->gross()->format() }}</dd></div>
                    @if ($invoice->paid_cents !== 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Paid</dt><dd class="text-gray-900">{{ $invoice->paid()->format() }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Outstanding</dt><dd class="font-medium text-gray-900">{{ $invoice->outstanding()->format() }}</dd></div>
                    @endif
                </dl>
                @if ($invoice->issue_date)<p class="mt-4 text-xs text-gray-400">Issued {{ $invoice->issue_date->format('Y-m-d') }}@if ($invoice->due_date) · due {{ $invoice->due_date->format('Y-m-d') }}@endif</p>@endif
            </div>

            @if ($invoice->isFinalized() && ! $isCredit && $invoice->status->value !== 'CANCELLED' && $invoice->status->value !== 'PAID')
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Record payment</h2>
                    <form method="POST" action="{{ route('finance.invoices.payments.store', $invoice) }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                            <input type="number" step="0.01" min="0" id="amount" name="amount" value="{{ number_format($invoice->outstanding()->cents / 100, 2, '.', '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <div>
                            <label for="paid_on" class="block text-sm font-medium text-gray-700">Paid on</label>
                            <input type="date" id="paid_on" name="paid_on" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        </div>
                        <button type="submit" class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Record</button>
                    </form>
                </div>
            @endif

            @if ($invoice->isFinalized())
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-gray-900">Email invoice</h2>
                    <p class="mt-1 text-xs text-gray-500">Sends the Factur-X PDF to the customer.</p>
                    <form method="POST" action="{{ route('finance.invoices.email', $invoice) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="email" name="email" value="{{ $invoice->customer?->email }}" placeholder="recipient@example.com"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="submit" class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Send</button>
                    </form>
                </div>
            @endif

            @if ($invoice->files->isNotEmpty())
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Source document</p>
                    @foreach ($invoice->files as $doc)
                        <a href="{{ route('files.show', $doc) }}" class="mt-1 block hover:underline">{{ $doc->displayTitle }}</a>
                    @endforeach
                </div>
            @endif

            @if ($invoice->creditNotes->isNotEmpty())
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400">Credit notes</p>
                    @foreach ($invoice->creditNotes as $note)
                        <a href="{{ route('finance.invoices.show', $note) }}" class="mt-1 block hover:underline">{{ $note->number ?? 'Draft #'.$note->id }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
