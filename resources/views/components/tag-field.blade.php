@props([
    'commit' => '',
    'placeholder' => '',
    'list' => null,
])

@php
    // Badge-chip tag input. Renders inside the PARENT Alpine scope (zkModule provides
    // tagList/tagDraft/commitTag/onTagInput/tagBackspace/removeTag over `tagsValue`),
    // so it is a drop-in for the old `<input x-model="tagsValue">`. Type text and press
    // Enter or a comma to add a chip; click × or backspace on an empty field to remove.
    // `commit` = an optional JS statement to run after a change (e.g. save() for modules
    // that persist on edit); modules with an explicit Save button pass nothing.
    $after = $commit ? '; '.$commit : '';
@endphp

<div {{ $attributes->class('mt-1 flex min-h-11 w-full flex-wrap items-center gap-1.5 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-2 py-1.5 text-sm transition focus-within:border-accent focus-within:ring-1 focus-within:ring-accent') }}
     @click="$refs.tagInput.focus()">
    <template x-for="tag in tagList()" :key="tag">
        <span class="inline-flex items-center gap-1 rounded-md bg-accent/10 py-0.5 pl-2 pr-1 text-xs font-medium text-accent">
            <span x-text="tag"></span>
            <button type="button" @click.stop="removeTag(tag){{ $after }}" class="rounded p-0.5 hover:bg-accent/15" aria-label="{{ __('common.delete') }}"><x-icon name="x-mark" class="h-3 w-3" /></button>
        </span>
    </template>
    <input type="text" x-ref="tagInput" x-model="tagDraft"
        @input="onTagInput(){{ $after }}"
        @keydown.enter.prevent="commitTag(){{ $after }}"
        @keydown.backspace="tagBackspace(){{ $after }}"
        @blur="commitTag(){{ $after }}"
        placeholder="{{ $placeholder }}"
        @if ($list) list="{{ $list }}" @endif
        class="min-w-[6rem] flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0 placeholder:text-gray-400 dark:placeholder:text-gray-500">
</div>
