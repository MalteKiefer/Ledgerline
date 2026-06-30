{{--
    Shared project form fields, used by both the global create form (with a
    customer picker) and the per-customer/edit forms (customer locked).

    Expects:
      $project        the model (empty when creating)
      $statuses       ProjectStatus options
      $types          ProjectType options
      $priorities     ProjectPriority options
      $lockedCustomer Customer|null  (when set, customer is fixed)
      $customers      iterable of customers for the picker (when not locked)
      $tagSuggestions list<string>   existing tag names
      $existingTags   list<string>   the project's current tag names
--}}
@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $selectedStatus = old('status', $project->status?->value);
    $selectedType = old('type', $project->type?->value);
    $selectedPriority = old('priority', $project->priority?->value ?? 'NORMAL');
@endphp

<div class="space-y-6">
    {{-- Customer: a searchable picker when creating globally, otherwise fixed. --}}
    @if ($lockedCustomer)
        <input type="hidden" name="customer_id" value="{{ $lockedCustomer->id }}">
        <div>
            <span class="block text-sm font-medium text-gray-700">Customer</span>
            <p class="mt-1 text-sm text-gray-900">{{ $lockedCustomer->name }}</p>
        </div>
    @else
        <x-customer-combobox :customers="$customers" :value="old('customer_id')" />
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="name" class="block text-sm font-medium text-gray-700">
                Name<span class="text-red-600"> *</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name', $project->name) }}" required
                @error('name') aria-invalid="true" @enderror class="{{ $input }}">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
            <input type="text" id="reference" name="reference" value="{{ old('reference', $project->reference) }}"
                @error('reference') aria-invalid="true" @enderror class="{{ $input }}">
            @error('reference')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">
                Type<span class="text-red-600"> *</span>
            </label>
            <select id="type" name="type" required @error('type') aria-invalid="true" @enderror class="{{ $input }}">
                <option value="" disabled @selected($selectedType === null)>Select a type…</option>
                @foreach ($types as $type)
                    <option value="{{ $type['value'] }}" @selected($selectedType === $type['value'])>{{ $type['label'] }}</option>
                @endforeach
            </select>
            @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">
                Status<span class="text-red-600"> *</span>
            </label>
            <select id="status" name="status" required @error('status') aria-invalid="true" @enderror class="{{ $input }}">
                <option value="" disabled @selected($selectedStatus === null)>Select a status…</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status['value'] }}" @selected($selectedStatus === $status['value'])>{{ $status['label'] }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="priority" class="block text-sm font-medium text-gray-700">
                Priority<span class="text-red-600"> *</span>
            </label>
            <select id="priority" name="priority" required @error('priority') aria-invalid="true" @enderror class="{{ $input }}">
                @foreach ($priorities as $priority)
                    <option value="{{ $priority['value'] }}" @selected($selectedPriority === $priority['value'])>{{ $priority['label'] }}</option>
                @endforeach
            </select>
            @error('priority')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700">Start date</label>
            <input type="date" id="start_date" name="start_date"
                value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}"
                @error('start_date') aria-invalid="true" @enderror class="{{ $input }}">
            @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">End date</label>
            <input type="date" id="end_date" name="end_date"
                value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}"
                @error('end_date') aria-invalid="true" @enderror class="{{ $input }}">
            @error('end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="budget" class="block text-sm font-medium text-gray-700">Budget</label>
            <input type="number" step="0.01" min="0" id="budget" name="budget"
                value="{{ old('budget', $project->budget) }}"
                @error('budget') aria-invalid="true" @enderror class="{{ $input }}">
            @error('budget')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="estimated_hours" class="block text-sm font-medium text-gray-700">Estimated hours</label>
            <input type="number" step="0.25" min="0" id="estimated_hours" name="estimated_hours"
                value="{{ old('estimated_hours', $project->estimated_hours) }}"
                @error('estimated_hours') aria-invalid="true" @enderror class="{{ $input }}">
            @error('estimated_hours')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <x-tag-input name="tags" :value="$existingTags" :suggestions="$tagSuggestions" />

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea id="description" name="description" rows="4"
            @error('description') aria-invalid="true" @enderror class="{{ $input }}">{{ old('description', $project->description) }}</textarea>
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
