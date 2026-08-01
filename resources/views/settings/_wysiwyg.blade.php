{{-- Rich-text editor (contenteditable + toolbar, DOMPurify-sanitised client side,
     re-sanitised server side on save). $name = form field, $html = initial HTML. --}}
@php $html = $html ?? ''; @endphp
@php
    $btn = 'flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10';
    $sep = '<span class="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700"></span>';
@endphp
<div x-data="wysiwyg()" class="mt-1 overflow-hidden rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
  <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 dark:border-gray-700 px-1.5 py-1">
    <select @change="heading($event.target.value); $event.target.selectedIndex = 0" class="h-7 rounded border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs py-0 pr-6">
      <option value="p">{{ __('settings.wysiwyg_paragraph') }}</option>
      <option value="h1">{{ __('settings.wysiwyg_h1') }}</option>
      <option value="h2">{{ __('settings.wysiwyg_h2') }}</option>
      <option value="h3">{{ __('settings.wysiwyg_h3') }}</option>
      <option value="blockquote">{{ __('settings.wysiwyg_quote') }}</option>
    </select>
    {!! $sep !!}
    <button type="button" @click="cmd('bold')" title="{{ __('settings.wysiwyg_bold') }}" class="{{ $btn }} font-bold">B</button>
    <button type="button" @click="cmd('italic')" title="{{ __('settings.wysiwyg_italic') }}" class="{{ $btn }} italic">I</button>
    <button type="button" @click="cmd('underline')" title="{{ __('settings.wysiwyg_underline') }}" class="{{ $btn }} underline">U</button>
    <button type="button" @click="cmd('strikeThrough')" title="{{ __('settings.wysiwyg_strike') }}" class="{{ $btn }} line-through">S</button>
    <label class="{{ $btn }} cursor-pointer" title="{{ __('settings.wysiwyg_color') }}">
      <span class="h-3.5 w-3.5 rounded-sm border border-gray-300" style="background:linear-gradient(135deg,#ef4444,#3b82f6)"></span>
      <input type="color" class="sr-only" @input="color($event.target.value)">
    </label>
    {!! $sep !!}
    <button type="button" @click="align('Left')" title="{{ __('settings.wysiwyg_align_left') }}" class="{{ $btn }}"><x-icon name="bars-3-bottom-left" class="h-4 w-4" /></button>
    <button type="button" @click="align('Center')" title="{{ __('settings.wysiwyg_align_center') }}" class="{{ $btn }}"><x-icon name="bars-3" class="h-4 w-4" /></button>
    <button type="button" @click="align('Right')" title="{{ __('settings.wysiwyg_align_right') }}" class="{{ $btn }}"><x-icon name="bars-3-bottom-right" class="h-4 w-4" /></button>
    {!! $sep !!}
    <button type="button" @click="cmd('insertUnorderedList')" title="{{ __('settings.wysiwyg_ul') }}" class="{{ $btn }} text-xs">•&nbsp;{{ __('settings.wysiwyg_list') }}</button>
    <button type="button" @click="cmd('insertOrderedList')" title="{{ __('settings.wysiwyg_ol') }}" class="{{ $btn }} text-xs">1.</button>
    {!! $sep !!}
    <button type="button" @click="link()" title="{{ __('settings.wysiwyg_link') }}" class="{{ $btn }}"><x-icon name="link" class="h-4 w-4" /></button>
    <button type="button" @click="unlink()" title="{{ __('settings.wysiwyg_unlink') }}" class="{{ $btn }}"><x-icon name="link-slash" class="h-4 w-4" /></button>
    <button type="button" @click="image()" title="{{ __('settings.wysiwyg_image') }}" class="{{ $btn }}"><x-icon name="photo" class="h-4 w-4" /></button>
    <button type="button" @click="hr()" title="{{ __('settings.wysiwyg_hr') }}" class="{{ $btn }} text-xs">―</button>
    {!! $sep !!}
    <button type="button" @click="cmd('removeFormat')" title="{{ __('settings.wysiwyg_clear') }}" class="{{ $btn }} text-xs text-gray-500">{{ __('settings.wysiwyg_clear') }}</button>
  </div>
  <div x-ref="area" contenteditable="true" @input="_sync()" @blur="_sync()"
       class="min-h-[220px] max-h-[460px] overflow-auto px-4 py-3 text-sm text-gray-900 dark:text-gray-100 focus:outline-none [&_a]:text-accent [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:font-semibold [&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-3 [&_blockquote]:text-gray-500 [&_img]:max-w-full [&_hr]:my-2">{!! $html !!}</div>
  <input type="hidden" name="{{ $name }}" x-ref="hidden" data-link-prompt="{{ __('settings.wysiwyg_link_prompt') }}" data-img-prompt="{{ __('settings.wysiwyg_image_prompt') }}">
</div>
