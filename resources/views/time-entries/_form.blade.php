@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $hours = old('hours', $entry->minutes !== null ? number_format($entry->minutes / 60, 2, '.', '') : '');
    $rate = old('rate', $entry->rate_cents ? number_format($entry->rate_cents / 100, 2, '.', '') : '');
    $selCurrency = old('currency', $entry->currency ?? 'EUR');
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="date" class="block text-sm font-medium text-gray-700">Date<span class="text-red-600"> *</span></label>
        <input type="date" id="date" name="date" value="{{ old('date', $entry->date?->format('Y-m-d')) }}" required class="{{ $input }}">
        @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="inline-flex items-center gap-2 pt-6 text-sm text-gray-700">
            <input type="checkbox" name="billable" value="1" @checked(old('billable', $entry->billable ?? true)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            Billable
        </label>
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700">Description<span class="text-red-600"> *</span></label>
        <input type="text" id="description" name="description" value="{{ old('description', $entry->description) }}" required class="{{ $input }}">
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="hours" class="block text-sm font-medium text-gray-700">Hours<span class="text-red-600"> *</span></label>
        <input type="number" step="0.01" min="0" id="hours" name="hours" value="{{ $hours }}" required class="{{ $input }}">
        @error('hours')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label for="rate" class="block text-sm font-medium text-gray-700">Rate / h</label>
            <input type="number" step="0.01" min="0" id="rate" name="rate" value="{{ $rate }}" placeholder="Default" class="{{ $input }}">
            <p class="mt-1 text-xs text-gray-400">Blank = project or customer default.</p>
        </div>
        <div>
            <label for="currency" class="block text-sm font-medium text-gray-700">Currency</label>
            <select id="currency" name="currency" class="{{ $input }}">
                @foreach ($currencies as $cur)
                    <option value="{{ $cur }}" @selected($selCurrency === $cur)>{{ $cur }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer</label>
        <select id="customer_id" name="customer_id" class="{{ $input }}">
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((int) old('customer_id', $entry->customer_id) === $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="project_id" class="block text-sm font-medium text-gray-700">Project</label>
        <select id="project_id" name="project_id" class="{{ $input }}">
            <option value="">—</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((int) old('project_id', $entry->project_id) === $project->id)>{{ $project->name }} ({{ $project->customer->name }})</option>
            @endforeach
        </select>
    </div>
</div>
