{{--
    Shared project form fields.

    Expects $project (an empty instance when creating) and $statuses, the
    ProjectStatus options. The enclosing <form>, CSRF token and submit button
    are provided by the including view.
--}}
@php
    $selectedStatus = old('status', $project->status?->value);
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium text-gray-700">
            Name<span class="text-red-600"> *</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $project->name) }}" required
            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('name')
            <p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
        <input type="text" id="reference" name="reference" value="{{ old('reference', $project->reference) }}"
            @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('reference')
            <p id="reference-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-gray-700">
            Status<span class="text-red-600"> *</span>
        </label>
        <select id="status" name="status" required
            @error('status') aria-invalid="true" aria-describedby="status-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
            <option value="" disabled @selected($selectedStatus === null)>Select a status…</option>
            @foreach ($statuses as $status)
                <option value="{{ $status['value'] }}" @selected($selectedStatus === $status['value'])>
                    {{ $status['label'] }}
                </option>
            @endforeach
        </select>
        @error('status')
            <p id="status-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="start_date" class="block text-sm font-medium text-gray-700">Start date</label>
        <input type="date" id="start_date" name="start_date"
            value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}"
            @error('start_date') aria-invalid="true" aria-describedby="start_date-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('start_date')
            <p id="start_date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="end_date" class="block text-sm font-medium text-gray-700">End date</label>
        <input type="date" id="end_date" name="end_date"
            value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}"
            @error('end_date') aria-invalid="true" aria-describedby="end_date-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('end_date')
            <p id="end_date-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="budget" class="block text-sm font-medium text-gray-700">Budget</label>
        <input type="number" step="0.01" min="0" id="budget" name="budget"
            value="{{ old('budget', $project->budget) }}"
            @error('budget') aria-invalid="true" aria-describedby="budget-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
        @error('budget')
            <p id="budget-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea id="description" name="description" rows="4"
            @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">{{ old('description', $project->description) }}</textarea>
        @error('description')
            <p id="description-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
