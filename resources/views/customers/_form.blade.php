{{--
    Shared customer form fields.

    Expects a $customer model (an empty instance when creating). The enclosing
    <form> element, CSRF token and submit button are provided by the including
    view, so this partial only renders the inputs.
--}}
@php
    $fields = [
        'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
        'email' => ['label' => 'Email', 'type' => 'email', 'required' => false],
        'phone' => ['label' => 'Phone', 'type' => 'text', 'required' => false],
        'vat_id' => ['label' => 'VAT ID', 'type' => 'text', 'required' => false],
        'street' => ['label' => 'Street', 'type' => 'text', 'required' => false],
        'postal_code' => ['label' => 'Postal code', 'type' => 'text', 'required' => false],
        'city' => ['label' => 'City', 'type' => 'text', 'required' => false],
        'country' => ['label' => 'Country', 'type' => 'text', 'required' => false],
    ];
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    @foreach ($fields as $name => $field)
        <div @class(['sm:col-span-2' => $name === 'name'])>
            <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
                {{ $field['label'] }}@if ($field['required'])<span class="text-red-600"> *</span>@endif
            </label>
            <input
                type="{{ $field['type'] }}"
                id="{{ $name }}"
                name="{{ $name }}"
                value="{{ old($name, $customer->{$name}) }}"
                @required($field['required'])
                @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
            @error($name)
                <p id="{{ $name }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endforeach

    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
        <textarea
            id="notes"
            name="notes"
            rows="4"
            @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">{{ old('notes', $customer->notes) }}</textarea>
        @error('notes')
            <p id="notes-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
