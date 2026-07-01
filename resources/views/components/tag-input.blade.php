@props([
    'name' => 'tags',
    'value' => [],
    'suggestions' => [],
    'label' => null,
])

@php
    $initial = collect(old($name, $value))->filter()->values()->all();
@endphp

<div x-data="tagInput(@js($initial))">
    <label for="{{ $name }}-input" class="block text-sm font-medium text-gray-700">{{ $label ?? __('pages.tag_input.label') }}</label>

    {{-- One hidden field per chip is what actually gets submitted. --}}
    <template x-for="(tag, index) in tags" :key="index">
        <input type="hidden" :name="@js($name) + '[]'" :value="tag">
    </template>

    <div class="mt-1 flex flex-wrap items-center gap-2 rounded-md border border-gray-300 p-2 shadow-sm focus-within:border-gray-500 focus-within:ring-1 focus-within:ring-gray-500">
        <template x-for="(tag, index) in tags" :key="index">
            <span class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-800">
                <span x-text="tag"></span>
                <button type="button" @click="remove(index)" class="text-gray-500 hover:text-gray-700"
                    aria-label="{{ __('pages.tag_input.remove_tag') }}">&times;</button>
            </span>
        </template>
        <input type="text" id="{{ $name }}-input" x-model="query" @keydown="onKey($event)" @blur="add()"
            list="{{ $name }}-suggestions" placeholder="{{ __('pages.tag_input.placeholder') }}"
            class="min-w-[8rem] flex-1 border-0 p-0 text-sm focus:ring-0">
    </div>

    <datalist id="{{ $name }}-suggestions">
        @foreach ($suggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>

    <p class="mt-1 text-xs text-gray-500">{{ __('pages.tag_input.help') }}</p>

    @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
