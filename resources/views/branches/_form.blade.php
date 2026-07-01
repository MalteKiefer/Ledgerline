{{--
    Shared branch (Niederlassung) form fields.

    Expects $branch (empty when creating) and $contacts (the customer's contacts,
    for the manager selector). The enclosing <form>, CSRF token and submit button
    come from the including view.
--}}
@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $selectedManager = old('manager_contact_id', $branch->manager_contact_id);
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium text-gray-700">
            {{ __('branches.form.name') }}<span class="text-red-600"> *</span>
        </label>
        <input type="text" id="name" name="name" value="{{ old('name', $branch->name) }}" required
            @error('name') aria-invalid="true" @enderror class="{{ $input }}">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label for="manager_contact_id" class="block text-sm font-medium text-gray-700">
            {{ __('branches.form.manager') }}
        </label>
        <select id="manager_contact_id" name="manager_contact_id"
            @error('manager_contact_id') aria-invalid="true" @enderror class="{{ $input }}">
            <option value="">{{ __('branches.form.manager_none') }}</option>
            @foreach ($contacts as $contact)
                <option value="{{ $contact->id }}" @selected((string) $selectedManager === (string) $contact->id)>
                    {{ $contact->name }}
                </option>
            @endforeach
        </select>
        @error('manager_contact_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        @if ($contacts->isEmpty())
            <p class="mt-1 text-sm text-gray-500">{{ __('branches.form.manager_empty_help') }}</p>
        @endif
    </div>

    <div class="sm:col-span-2">
        <label for="street" class="block text-sm font-medium text-gray-700">{{ __('branches.form.street') }}</label>
        <input type="text" id="street" name="street" value="{{ old('street', $branch->street) }}"
            @error('street') aria-invalid="true" @enderror class="{{ $input }}">
        @error('street')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="postal_code" class="block text-sm font-medium text-gray-700">{{ __('branches.form.postal_code') }}</label>
        <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $branch->postal_code) }}"
            @error('postal_code') aria-invalid="true" @enderror class="{{ $input }}">
        @error('postal_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="city" class="block text-sm font-medium text-gray-700">{{ __('branches.form.city') }}</label>
        <input type="text" id="city" name="city" value="{{ old('city', $branch->city) }}"
            @error('city') aria-invalid="true" @enderror class="{{ $input }}">
        @error('city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <x-country-combobox name="country" :value="$branch->country" />
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('branches.form.phone') }}</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $branch->phone) }}"
            @error('phone') aria-invalid="true" @enderror class="{{ $input }}">
        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700">{{ __('branches.form.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email', $branch->email) }}"
            @error('email') aria-invalid="true" @enderror class="{{ $input }}">
        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
