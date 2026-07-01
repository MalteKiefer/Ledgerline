<x-layouts.app :title="__('invoices.import.review.title')">
    @php
        $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
        $c = $parsed['customer'];
        $lineRows = collect($parsed['lines'])->map(fn (array $l): array => [
            'description' => $l['description'],
            'quantity' => $l['quantity'],
            'unit' => $l['unit'],
            'unit_price' => number_format($l['unit_price'], 2, '.', ''),
            'tax_rate' => $l['tax_rate'],
        ])->values();
    @endphp

    <x-finance-nav />
    <p class="text-sm text-gray-500"><a href="{{ route('finance.invoices.import.create') }}" class="hover:underline">{{ __('invoices.import.review.breadcrumb') }}</a></p>
    <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('invoices.import.review.heading') }}</h1>
        @if (($total ?? 0) > 0)
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">{{ __('invoices.import.review.progress', ['position' => $position, 'total' => $total]) }}</span>
                <x-confirm-action :action="route('finance.invoices.import.skip')" method="POST"
                    :trigger="__('invoices.import.review.skip')"
                    trigger-class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    :message="__('invoices.import.review.skip_confirm')"
                    :confirm="__('invoices.import.review.skip')"
                    confirm-class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700" />
            </div>
        @endif
    </div>
    <p class="mt-1 text-sm text-gray-600">
        {{ __('invoices.import.review.read_from') }} <strong>{{ $file->name }}</strong>.
        <a href="{{ route('files.download', $file) }}" target="_blank" rel="noopener" class="text-gray-900 underline">{{ __('invoices.import.review.open_pdf') }}</a>.
        {{ __('invoices.import.review.parsed_totals', ['net' => number_format($parsed['net'] ?? 0, 2), 'vat' => number_format($parsed['tax'] ?? 0, 2), 'gross' => number_format($parsed['gross'] ?? 0, 2)]) }}
    </p>

    <form method="POST" action="{{ route('finance.invoices.import.store') }}" class="mt-6 space-y-6">
        @csrf
        <input type="hidden" name="file_id" value="{{ $file->id }}">

        {{-- Header --}}
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="number" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.invoice_number') }}<span class="text-red-600"> *</span></label>
                    <input type="text" id="number" name="number" value="{{ old('number', $parsed['number']) }}" required class="{{ $input }}">
                    @error('number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-400">{{ __('invoices.import.review.counter_hint') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="issue_date" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.issue_date') }}<span class="text-red-600"> *</span></label>
                        <input type="date" id="issue_date" name="issue_date" value="{{ old('issue_date', $parsed['issue_date']) }}" required class="{{ $input }}">
                        @error('issue_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.due_date') }}</label>
                        <input type="date" id="due_date" name="due_date" value="{{ old('due_date', $parsed['due_date']) }}" class="{{ $input }}">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.status') }}</label>
                        <select id="status" name="status" class="{{ $input }}">
                            @foreach ($statuses as $s)
                                <option value="{{ $s['value'] }}" @selected(old('status', 'PAID') === $s['value'])>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="currency" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.currency') }}</label>
                        <select id="currency" name="currency" class="{{ $input }}">
                            @foreach ($currencies as $cur)
                                <option value="{{ $cur }}" @selected(old('currency', $parsed['currency']) === $cur)>{{ $cur }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="tax_mode" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.tax_mode') }}</label>
                        <select id="tax_mode" name="tax_mode" class="{{ $input }}">
                            @foreach ($taxModes as $m)
                                <option value="{{ $m['value'] }}" @selected(old('tax_mode', ($parsed['small_business'] ?? false) ? 'SMALL_BUSINESS' : 'STANDARD') === $m['value'])>{{ $m['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </section>

        {{-- Customer --}}
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
            x-data="{ mode: '{{ old('customer_mode', $matchedCustomerId ? 'existing' : 'new') }}' }">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('invoices.import.review.customer') }}</h2>
            <div class="mt-3 flex gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="radio" name="customer_mode" value="existing" x-model="mode"> {{ __('invoices.import.review.existing') }}</label>
                <label class="flex items-center gap-2"><input type="radio" name="customer_mode" value="new" x-model="mode"> {{ __('invoices.import.review.create_new') }}</label>
            </div>

            <div x-show="mode === 'existing'" class="mt-3">
                <label for="customer_id" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.customer') }}</label>
                <select id="customer_id" name="customer_id" class="{{ $input }}">
                    <option value="">{{ __('invoices.import.review.select') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((int) old('customer_id', $matchedCustomerId) === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
                @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="mode === 'new'" x-cloak class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="new_customer_name" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.name') }}<span class="text-red-600"> *</span></label>
                    <input type="text" id="new_customer_name" name="new_customer_name" value="{{ old('new_customer_name', $c['name']) }}" class="{{ $input }}">
                    @error('new_customer_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="new_customer_vat_id" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.vat_id') }}</label>
                    <input type="text" id="new_customer_vat_id" name="new_customer_vat_id" value="{{ old('new_customer_vat_id', $c['vat_id']) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="new_customer_street" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.street') }}</label>
                    <input type="text" id="new_customer_street" name="new_customer_street" value="{{ old('new_customer_street', $c['street']) }}" class="{{ $input }}">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="new_customer_postal_code" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.postal_code') }}</label>
                        <input type="text" id="new_customer_postal_code" name="new_customer_postal_code" value="{{ old('new_customer_postal_code', $c['postal_code']) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="new_customer_city" class="block text-sm font-medium text-gray-700">{{ __('invoices.import.review.city') }}</label>
                        <input type="text" id="new_customer_city" name="new_customer_city" value="{{ old('new_customer_city', $c['city']) }}" class="{{ $input }}">
                    </div>
                </div>
            </div>
        </section>

        <datalist id="units-list">
            @foreach ($units as $u)
                <option value="{{ $u->code }}">{{ $u->label() }}</option>
            @endforeach
        </datalist>

        {{-- Lines --}}
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm" x-data="invoiceLines(@js($lineRows))">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('invoices.import.review.lines') }}</h2>
                <button type="button" @click="add()" class="text-sm text-gray-700 hover:text-gray-900">{{ __('invoices.import.review.add_line') }}</button>
            </div>
            <div class="mt-3 space-y-2">
                <template x-for="(line, i) in lines" :key="i">
                    <div class="grid grid-cols-12 gap-2">
                        <input type="text" :name="`lines[${i}][description]`" x-model="line.description" placeholder="{{ __('invoices.import.review.description') }}"
                            class="col-span-5 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="number" step="0.01" :name="`lines[${i}][quantity]`" x-model="line.quantity" placeholder="{{ __('invoices.import.review.qty') }}"
                            class="col-span-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="text" :name="`lines[${i}][unit]`" x-model="line.unit" placeholder="{{ __('invoices.import.review.unit') }}" list="units-list"
                            class="col-span-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="number" step="0.01" :name="`lines[${i}][unit_price]`" x-model="line.unit_price" placeholder="{{ __('invoices.import.review.net_price') }}"
                            class="col-span-2 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="number" min="0" max="100" :name="`lines[${i}][tax_rate]`" x-model="line.tax_rate" placeholder="{{ __('invoices.import.review.vat_percent') }}"
                            class="col-span-2 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <button type="button" @click="remove(i)" class="col-span-1 text-sm text-red-600 hover:text-red-800">✕</button>
                    </div>
                </template>
            </div>
            <p class="mt-3 text-right text-sm text-gray-500">{{ __('invoices.import.review.net_preview') }} <span class="font-medium text-gray-900" x-text="net.toFixed(2)"></span></p>
        </section>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('invoices.import.review.import_invoice') }}</button>
            <a href="{{ route('finance.invoices.import.create') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('invoices.import.review.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
