@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $amount = old('amount', $entry->amount_cents !== null ? number_format($entry->amount_cents / 100, 2, '.', '') : '');
    $selCurrency = old('currency', $entry->currency ?? 'EUR');
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="date" class="block text-sm font-medium text-gray-700">{{ __('income.form.date') }}<span class="text-red-600"> *</span></label>
        <input type="date" id="date" name="date" value="{{ old('date', $entry->date?->format('Y-m-d')) }}" required class="{{ $input }}">
        @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label for="amount" class="block text-sm font-medium text-gray-700">{{ __('income.form.amount') }}<span class="text-red-600"> *</span></label>
            <input type="number" step="0.01" min="0" id="amount" name="amount" value="{{ $amount }}" required class="{{ $input }}">
            @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="currency" class="block text-sm font-medium text-gray-700">{{ __('income.form.currency') }}</label>
            <select id="currency" name="currency" class="{{ $input }}">
                @foreach ($currencies as $cur)
                    <option value="{{ $cur }}" @selected($selCurrency === $cur)>{{ $cur }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700">{{ __('income.form.description') }}<span class="text-red-600"> *</span></label>
        <input type="text" id="description" name="description" value="{{ old('description', $entry->description) }}" required class="{{ $input }}">
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="customer_id" class="block text-sm font-medium text-gray-700">{{ __('income.form.customer') }}</label>
        <select id="customer_id" name="customer_id" class="{{ $input }}">
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((int) old('customer_id', $entry->customer_id) === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project_id" class="block text-sm font-medium text-gray-700">{{ __('income.form.project') }}</label>
        <select id="project_id" name="project_id" class="{{ $input }}">
            <option value="">—</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((int) old('project_id', $entry->project_id) === $project->id)>{{ $project->name }} ({{ $project->customer->name }})</option>
            @endforeach
        </select>
    </div>
</div>
