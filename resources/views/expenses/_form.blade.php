@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $selCategory = old('category', $expense->category?->value);
    $selStatus = old('payment_status', $expense->payment_status?->value ?? 'OPEN');
    $selCurrency = old('currency', $expense->currency ?? 'EUR');
    $amount = old('amount', $expense->amount_cents !== null ? number_format($expense->amount_cents / 100, 2, '.', '') : '');
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="date" class="block text-sm font-medium text-gray-700">Date<span class="text-red-600"> *</span></label>
        <input type="date" id="date" name="date" value="{{ old('date', $expense->date?->format('Y-m-d')) }}" required class="{{ $input }}">
        @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="vendor" class="block text-sm font-medium text-gray-700">Vendor</label>
        <input type="text" id="vendor" name="vendor" value="{{ old('vendor', $expense->vendor) }}" class="{{ $input }}">
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700">Description<span class="text-red-600"> *</span></label>
        <input type="text" id="description" name="description" value="{{ old('description', $expense->description) }}" required class="{{ $input }}">
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="category" class="block text-sm font-medium text-gray-700">Category<span class="text-red-600"> *</span></label>
        <select id="category" name="category" required class="{{ $input }}">
            <option value="" disabled @selected($selCategory === null)>Select…</option>
            @foreach ($categories as $c)
                <option value="{{ $c['value'] }}" @selected($selCategory === $c['value'])>{{ $c['label'] }}</option>
            @endforeach
        </select>
        @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="category_custom" class="block text-sm font-medium text-gray-700">Custom category (optional)</label>
        <input type="text" id="category_custom" name="category_custom" value="{{ old('category_custom', $expense->category_custom) }}" class="{{ $input }}">
    </div>

    <div>
        <label for="amount" class="block text-sm font-medium text-gray-700">Gross amount<span class="text-red-600"> *</span></label>
        <input type="number" step="0.01" min="0" id="amount" name="amount" value="{{ $amount }}" required class="{{ $input }}">
        @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
            <select id="currency" name="currency" class="{{ $input }}">
                @foreach ($currencies as $cur)
                    <option value="{{ $cur }}" @selected($selCurrency === $cur)>{{ $cur }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="tax_rate" class="block text-sm font-medium text-gray-700">VAT %</label>
            <input type="number" min="0" max="100" id="tax_rate" name="tax_rate" value="{{ old('tax_rate', $expense->tax_rate ?? 19) }}" class="{{ $input }}">
        </div>
    </div>

    <div>
        <label for="payment_status" class="block text-sm font-medium text-gray-700">Payment status</label>
        <select id="payment_status" name="payment_status" class="{{ $input }}">
            @foreach ($statuses as $s)
                <option value="{{ $s['value'] }}" @selected($selStatus === $s['value'])>{{ $s['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="paid_on" class="block text-sm font-medium text-gray-700">Paid on</label>
        <input type="date" id="paid_on" name="paid_on" value="{{ old('paid_on', $expense->paid_on?->format('Y-m-d')) }}" class="{{ $input }}">
    </div>

    <div>
        <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label>
        <select id="customer_id" name="customer_id" class="{{ $input }}">
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((int) old('customer_id', $expense->customer_id) === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project_id" class="block text-sm font-medium text-gray-700">Project</label>
        <select id="project_id" name="project_id" class="{{ $input }}">
            <option value="">—</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((int) old('project_id', $expense->project_id) === $project->id)>{{ $project->name }} ({{ $project->customer->name }})</option>
            @endforeach
        </select>
    </div>

    <div class="sm:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="billable" value="1" @checked(old('billable', $expense->billable)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            Billable to the customer (can be added to an invoice)
        </label>
    </div>

    <div class="sm:col-span-2">
        <x-tag-input name="labels" :value="old('labels', $expense->labels ?? [])" label="Labels" />
    </div>
</div>
