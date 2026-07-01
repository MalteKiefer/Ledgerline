@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $showImports = $showImports ?? false;
    $lineRows = $invoice->exists
        ? $invoice->lines->map(fn ($l): array => [
            'description' => $l->description,
            'quantity' => (float) $l->quantity,
            'unit' => $l->unit,
            'unit_price' => number_format($l->unit_price_cents / 100, 2, '.', ''),
            'tax_rate' => $l->tax_rate,
        ])->values()
        : collect();
    $selCustomer = old('customer_id', $invoice->customer_id);
    $selCurrency = old('currency', $invoice->currency ?? 'EUR');
    $selLanguage = old('language', $invoice->language ?? 'de');
    $selTaxMode = old('tax_mode', $invoice->tax_mode?->value ?? 'STANDARD');
    $discount = old('discount', $invoice->discount_cents ? number_format($invoice->discount_cents / 100, 2, '.', '') : '');
@endphp

<div class="space-y-6">
    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer<span class="text-red-600"> *</span></label>
                <select id="customer_id" name="customer_id" required class="{{ $input }}">
                    <option value="" disabled @selected(! $selCustomer)>Select…</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) $selCustomer === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="issue_date" class="block text-sm font-medium text-gray-700">Issue date<span class="text-red-600"> *</span></label>
                    <input type="date" id="issue_date" name="issue_date" value="{{ old('issue_date', $invoice->issue_date?->format('Y-m-d')) }}" required class="{{ $input }}">
                    @error('issue_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700">Due date</label>
                    <input type="date" id="due_date" name="due_date" value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}" class="{{ $input }}">
                    @error('due_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label for="language" class="block text-sm font-medium text-gray-700">Language</label>
                    <select id="language" name="language" class="{{ $input }}">
                        @foreach ($languages as $code => $label)
                            <option value="{{ $code }}" @selected($selLanguage === $code)>{{ strtoupper($code) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
                    <select id="currency" name="currency" class="{{ $input }}">
                        @foreach ($currencies as $cur)
                            <option value="{{ $cur }}" @selected($selCurrency === $cur)>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_terms_days" class="block text-sm font-medium text-gray-700">Terms (days)</label>
                    <input type="number" min="0" max="365" id="payment_terms_days" name="payment_terms_days" value="{{ old('payment_terms_days', $invoice->payment_terms_days ?? 14) }}" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label for="tax_mode" class="block text-sm font-medium text-gray-700">Tax mode</label>
                <select id="tax_mode" name="tax_mode" class="{{ $input }}">
                    @foreach ($taxModes as $m)
                        <option value="{{ $m['value'] }}" @selected($selTaxMode === $m['value'])>{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <datalist id="units-list">
        @foreach ($units as $u)
            <option value="{{ $u->code }}">{{ $u->label() }}</option>
        @endforeach
    </datalist>

    {{-- Line editor --}}
    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" x-data="invoiceLines(@js($lineRows))">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Lines</h2>
            <button type="button" @click="add()" class="text-sm text-gray-700 hover:text-gray-900">+ Add line</button>
        </div>
        <div class="mt-3 space-y-2">
            <template x-for="(line, i) in lines" :key="i">
                <div class="grid grid-cols-12 gap-2">
                    <input type="text" :name="`lines[${i}][description]`" x-model="line.description" placeholder="Description"
                        class="col-span-5 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="number" step="0.01" :name="`lines[${i}][quantity]`" x-model="line.quantity" placeholder="Qty"
                        class="col-span-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="text" :name="`lines[${i}][unit]`" x-model="line.unit" placeholder="Unit" list="units-list"
                        class="col-span-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="number" step="0.01" :name="`lines[${i}][unit_price]`" x-model="line.unit_price" placeholder="Net price"
                        class="col-span-2 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <input type="number" min="0" max="100" :name="`lines[${i}][tax_rate]`" x-model="line.tax_rate" placeholder="VAT%"
                        class="col-span-2 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="button" @click="remove(i)" class="col-span-1 text-sm text-red-600 hover:text-red-800">✕</button>
                </div>
            </template>
        </div>
        <p class="mt-3 text-right text-sm text-gray-500">Net preview: <span class="font-medium text-gray-900" x-text="net.toFixed(2)"></span></p>

        <div class="mt-4 grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 sm:grid-cols-2">
            <div>
                <label for="discount" class="block text-sm font-medium text-gray-700">Discount (net)</label>
                <input type="number" step="0.01" min="0" id="discount" name="discount" value="{{ $discount }}" class="{{ $input }}">
            </div>
        </div>
    </section>

    @if ($showImports && ($importableTime->isNotEmpty() || $importableExpenses->isNotEmpty()))
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Import unbilled items</h2>
            <p class="mt-1 text-sm text-gray-600">Selected time and expenses are added as lines and marked billed on finalisation.</p>
            <div class="mt-3 space-y-1 text-sm">
                @foreach ($importableTime as $t)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="import[]" value="time:{{ $t->id }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        <span>{{ $t->date?->format('Y-m-d') }} · {{ $t->description }} · {{ number_format($t->hours(), 2) }} h × {{ $t->rate()->format() }} @if ($t->customer) — {{ $t->customer->name }}@endif</span>
                    </label>
                @endforeach
                @foreach ($importableExpenses as $e)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="import[]" value="expense:{{ $e->id }}" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        <span>{{ $e->date?->format('Y-m-d') }} · {{ $e->description }} · {{ $e->net()->format() }} net @if ($e->customer) — {{ $e->customer->name }}@endif</span>
                    </label>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="intro_text" class="block text-sm font-medium text-gray-700">Intro text</label>
                <textarea id="intro_text" name="intro_text" rows="3" class="{{ $input }}">{{ old('intro_text', $invoice->intro_text) }}</textarea>
            </div>
            <div>
                <label for="closing_text" class="block text-sm font-medium text-gray-700">Closing text</label>
                <textarea id="closing_text" name="closing_text" rows="3" class="{{ $input }}">{{ old('closing_text', $invoice->closing_text) }}</textarea>
            </div>
        </div>
    </section>
</div>
