@props([
    'customers',
    'name' => 'customer_id',
    'value' => null,
    'label' => 'Customer',
    'required' => true,
])

@php
    $options = collect($customers)
        ->map(fn ($customer) => ['value' => $customer->id, 'label' => $customer->name])
        ->values()
        ->all();
    $initial = old($name, $value);
@endphp

<div x-data="selectCombobox(@js($options), @js((string) $initial))">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif
    </label>

    <input type="hidden" name="{{ $name }}" :value="selected">

    <div class="relative mt-1">
        <input type="text" id="{{ $name }}" role="combobox" autocomplete="off"
            aria-controls="{{ $name }}-listbox" :aria-expanded="open.toString()"
            x-model="query" @focus="open = true" @input="syncFromQuery()"
            @keydown.escape="open = false" @click="open = true"
            placeholder="Type to search a customer…"
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">

        <ul x-show="open" x-cloak @click.outside="open = false" id="{{ $name }}-listbox" role="listbox"
            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
            <template x-for="option in filtered" :key="option.value">
                <li role="option" :aria-selected="(String(selected) === String(option.value)).toString()"
                    @click="choose(option)"
                    class="cursor-pointer px-3 py-2 hover:bg-gray-100"
                    :class="{ 'bg-gray-100 font-medium': String(selected) === String(option.value) }"
                    x-text="option.label"></li>
            </template>
            <template x-if="filtered.length === 0">
                <li class="px-3 py-2 text-gray-500">No matching customer.</li>
            </template>
        </ul>
    </div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
