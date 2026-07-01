{{--
    Shared customer form fields.

    Expects a $customer model (an empty instance when creating). The enclosing
    <form>, CSRF token and submit button are provided by the including view.
--}}
@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
@endphp

<div class="space-y-8">
    <section>
        <h2 class="text-sm font-semibold text-gray-900">{{ __('customers.form.section_company') }}</h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium text-gray-700">
                    {{ __('customers.form.name') }}<span class="text-red-600"> *</span>
                </label>
                <input type="text" id="name" name="name" value="{{ old('name', $customer->name) }}" required
                    @error('name') aria-invalid="true" aria-describedby="name-error" @enderror class="{{ $input }}">
                @error('name')<p id="name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('customers.form.email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $customer->email) }}"
                    @error('email') aria-invalid="true" @enderror class="{{ $input }}">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('customers.form.phone') }}</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                    @error('phone') aria-invalid="true" @enderror class="{{ $input }}">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="website" class="block text-sm font-medium text-gray-700">{{ __('customers.form.website') }}</label>
                <input type="url" id="website" name="website" value="{{ old('website', $customer->website) }}"
                    placeholder="https://example.com"
                    @error('website') aria-invalid="true" @enderror class="{{ $input }}">
                @error('website')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="vat_id" class="block text-sm font-medium text-gray-700">{{ __('customers.form.vat_id') }}</label>
                <input type="text" id="vat_id" name="vat_id" value="{{ old('vat_id', $customer->vat_id) }}"
                    @error('vat_id') aria-invalid="true" @enderror class="{{ $input }}">
                @error('vat_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="default_rate" class="block text-sm font-medium text-gray-700">{{ __('customers.form.default_rate') }}</label>
                <input type="number" step="0.01" min="0" id="default_rate" name="default_rate"
                    value="{{ old('default_rate', $customer->default_rate_cents ? number_format($customer->default_rate_cents / 100, 2, '.', '') : '') }}"
                    @error('default_rate_cents') aria-invalid="true" @enderror class="{{ $input }}">
                <p class="mt-1 text-xs text-gray-400">{{ __('customers.form.default_rate_help') }}</p>
            </div>
        </div>
    </section>

    <section>
        <h2 class="text-sm font-semibold text-gray-900">{{ __('customers.form.section_address') }}</h2>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="street" class="block text-sm font-medium text-gray-700">{{ __('customers.form.street') }}</label>
                <input type="text" id="street" name="street" value="{{ old('street', $customer->street) }}"
                    @error('street') aria-invalid="true" @enderror class="{{ $input }}">
                @error('street')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="postal_code" class="block text-sm font-medium text-gray-700">{{ __('customers.form.postal_code') }}</label>
                <input type="text" id="postal_code" name="postal_code"
                    value="{{ old('postal_code', $customer->postal_code) }}"
                    @error('postal_code') aria-invalid="true" @enderror class="{{ $input }}">
                @error('postal_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="city" class="block text-sm font-medium text-gray-700">{{ __('customers.form.city') }}</label>
                <input type="text" id="city" name="city" value="{{ old('city', $customer->city) }}"
                    @error('city') aria-invalid="true" @enderror class="{{ $input }}">
                @error('city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <x-country-combobox name="country" :value="$customer->country" />
            </div>
        </div>
    </section>

    <section>
        <h2 class="text-sm font-semibold text-gray-900">{{ __('customers.form.section_notes') }}</h2>
        <div class="mt-3">
            <textarea id="notes" name="notes" rows="4"
                @error('notes') aria-invalid="true" @enderror class="{{ $input }}">{{ old('notes', $customer->notes) }}</textarea>
            @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </section>
</div>
