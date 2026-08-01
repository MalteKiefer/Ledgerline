{{-- Tiny rich-text editor (contenteditable + toolbar, DOMPurify-sanitised client
     side, re-sanitised server side on save). $name = form field, $html = initial
     (already sanitised). --}}
@php $html = $html ?? ''; @endphp
<div x-data="wysiwyg()" class="mt-1 overflow-hidden rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
  <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 dark:border-gray-700 px-1.5 py-1">
    <button type="button" @click="cmd('bold')" title="{{ __('settings.wysiwyg_bold') }}" class="h-7 w-7 rounded font-bold text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10">B</button>
    <button type="button" @click="cmd('italic')" title="{{ __('settings.wysiwyg_italic') }}" class="h-7 w-7 rounded italic text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10">I</button>
    <button type="button" @click="cmd('underline')" title="{{ __('settings.wysiwyg_underline') }}" class="h-7 w-7 rounded text-gray-700 underline dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10">U</button>
    <span class="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700"></span>
    <button type="button" @click="cmd('insertUnorderedList')" title="{{ __('settings.wysiwyg_ul') }}" class="h-7 rounded px-1.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10">•&nbsp;{{ __('settings.wysiwyg_list') }}</button>
    <button type="button" @click="cmd('insertOrderedList')" title="{{ __('settings.wysiwyg_ol') }}" class="h-7 rounded px-1.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10">1.</button>
    <button type="button" @click="link()" title="{{ __('settings.wysiwyg_link') }}" class="flex h-7 w-7 items-center justify-center rounded text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10"><x-icon name="link" class="h-4 w-4" /></button>
    <button type="button" @click="cmd('removeFormat')" title="{{ __('settings.wysiwyg_clear') }}" class="h-7 rounded px-1.5 text-xs text-gray-500 hover:bg-black/5 dark:hover:bg-white/10">{{ __('settings.wysiwyg_clear') }}</button>
  </div>
  <div x-ref="area" contenteditable="true" @input="_sync()" @blur="_sync()"
       class="min-h-[110px] max-h-72 overflow-auto px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none [&_a]:text-accent [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5">{!! $html !!}</div>
  <input type="hidden" name="{{ $name }}" x-ref="hidden" data-link-prompt="{{ __('settings.wysiwyg_link_prompt') }}">
</div>
