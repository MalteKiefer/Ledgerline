@props(['id' => 'file', 'name' => 'file'])

{{-- Generalised drag-and-drop file field, reused by every upload form. --}}
<div x-data="dropzone"
    @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="onDrop($event)"
    :class="over ? 'border-gray-800 bg-gray-50' : 'border-gray-300'"
    class="rounded-md border-2 border-dashed px-4 py-6 text-center transition-colors">
    <input type="file" name="{{ $name }}" id="{{ $id }}" x-ref="input" @change="onChange()" required class="sr-only">
    <label for="{{ $id }}" class="cursor-pointer text-sm text-gray-600">
        <span x-show="! fileName">
            {{ __('pages.dropzone.drag_prompt') }} <span class="font-medium text-gray-900 underline">{{ __('pages.dropzone.browse') }}</span>
        </span>
        <span x-show="fileName" x-cloak x-text="fileName" class="font-medium text-gray-900"></span>
    </label>
</div>
@error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
