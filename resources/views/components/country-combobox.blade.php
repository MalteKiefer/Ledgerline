@props([
    'name' => 'country',
    'value' => null,
    'label' => null,
    'required' => false,
    'id' => null,
])

@php
    $fieldId = $id ?? $name;
    $options = \App\Support\Countries::options();
    $initial = old($name, $value);
@endphp

{{-- Type-ahead country picker. Submits an ISO alpha-2 code; shows name + flag. --}}
<div x-data="countryCombobox(@js($options), @js($initial))">
    <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700">
        {{ $label ?? __('pages.country.label') }}@if ($required)<span class="text-red-600"> *</span>@endif
    </label>

    <input type="hidden" name="{{ $name }}" :value="selected">

    <div class="relative mt-1">
        <div class="flex items-center gap-2 rounded-md border border-gray-300 px-3 shadow-sm focus-within:border-gray-500 focus-within:ring-1 focus-within:ring-gray-500">
            <span class="text-lg leading-none" x-text="selectedFlag" aria-hidden="true"></span>
            <input type="text" id="{{ $fieldId }}" role="combobox" autocomplete="off"
                aria-controls="{{ $fieldId }}-listbox" :aria-expanded="open.toString()"
                x-model="query" @focus="open = true" @input="syncFromQuery()"
                @keydown.escape="open = false" @click="open = true"
                placeholder="{{ __('pages.country.placeholder') }}"
                @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror
                class="w-full border-0 bg-transparent py-2 p-0 focus:ring-0 sm:text-sm">
        </div>

        <ul x-show="open" x-cloak @click.outside="open = false" id="{{ $fieldId }}-listbox" role="listbox"
            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
            <template x-for="option in filtered" :key="option.value">
                <li role="option" :aria-selected="(selected === option.value).toString()"
                    @click="choose(option)"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-gray-100"
                    :class="{ 'bg-gray-100 font-medium': selected === option.value }">
                    <span class="text-lg leading-none" x-text="option.flag" aria-hidden="true"></span>
                    <span x-text="option.label"></span>
                </li>
            </template>
            <template x-if="filtered.length === 0">
                <li class="px-3 py-2 text-gray-500">{{ __('pages.country.no_match') }}</li>
            </template>
        </ul>
    </div>

    @error($name)
        <p id="{{ $fieldId }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
