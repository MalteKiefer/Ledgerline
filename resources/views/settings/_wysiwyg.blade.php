{{-- Rich-text editor (contenteditable + toolbar, in-editor modals, DOMPurify-
     sanitised client + server). $name = form field, $html = initial HTML. --}}
@php $html = $html ?? ''; @endphp
@php
    $btn = 'flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-gray-700 dark:text-gray-200 hover:bg-black/5 dark:hover:bg-white/10';
    $sep = '<span class="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700"></span>';
    $inp = 'w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm shadow-sm focus:border-accent focus:ring-accent';
    $fonts = [
        '' => __('settings.wysiwyg_font_default'),
        'Inter, sans-serif' => 'Inter', 'Roboto, sans-serif' => 'Roboto', "'Open Sans', sans-serif" => 'Open Sans',
        'Lato, sans-serif' => 'Lato', 'Montserrat, sans-serif' => 'Montserrat', "'Source Sans 3', sans-serif" => 'Source Sans 3',
        'Merriweather, serif' => 'Merriweather', "'Playfair Display', serif" => 'Playfair Display',
        'Georgia, serif' => 'Georgia', 'Arial, sans-serif' => 'Arial',
    ];
@endphp
<div x-data="wysiwyg()" class="mt-1 overflow-hidden rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800">
  <div class="flex flex-wrap items-center gap-0.5 border-b border-gray-200 dark:border-gray-700 px-1.5 py-1">
    <select @change="heading($event.target.value); $event.target.selectedIndex = 0" class="h-7 rounded border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs py-0 pr-6" title="{{ __('settings.wysiwyg_block') }}">
      <option value="p">{{ __('settings.wysiwyg_paragraph') }}</option>
      <option value="h1">{{ __('settings.wysiwyg_h1') }}</option>
      <option value="h2">{{ __('settings.wysiwyg_h2') }}</option>
      <option value="h3">{{ __('settings.wysiwyg_h3') }}</option>
      <option value="blockquote">{{ __('settings.wysiwyg_quote') }}</option>
    </select>
    <select @change="setFont($event.target.value); $event.target.selectedIndex = 0" class="h-7 rounded border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs py-0 pr-6" title="{{ __('settings.wysiwyg_font') }}">
      @foreach ($fonts as $val => $label)
        <option value="{{ $val }}" style="font-family:{{ $val ?: 'inherit' }}">{{ $label }}</option>
      @endforeach
    </select>
    <select @change="setSize($event.target.value); $event.target.selectedIndex = 0" class="h-7 rounded border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs py-0 pr-6" title="{{ __('settings.wysiwyg_size') }}">
      <option value="">{{ __('settings.wysiwyg_size') }}</option>
      @foreach ([8,9,10,11,12,14,16,18,24,32] as $pt)
        <option value="{{ $pt }}">{{ $pt }} pt</option>
      @endforeach
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
    <button type="button" @click="openLink()" title="{{ __('settings.wysiwyg_link') }}" class="{{ $btn }}"><x-icon name="link" class="h-4 w-4" /></button>
    <button type="button" @click="unlink()" title="{{ __('settings.wysiwyg_unlink') }}" class="{{ $btn }}"><x-icon name="link-slash" class="h-4 w-4" /></button>
    <button type="button" @click="openImage()" title="{{ __('settings.wysiwyg_image') }}" class="{{ $btn }}"><x-icon name="photo" class="h-4 w-4" /></button>
    <button type="button" @click="hr()" title="{{ __('settings.wysiwyg_hr') }}" class="{{ $btn }} text-xs">―</button>
    {!! $sep !!}
    <button type="button" @click="cmd('removeFormat')" title="{{ __('settings.wysiwyg_clear') }}" class="{{ $btn }} text-xs text-gray-500">{{ __('settings.wysiwyg_clear') }}</button>
  </div>
  <div x-ref="area" contenteditable="true" @input="_sync()" @blur="_sync()"
       class="min-h-[220px] max-h-[460px] overflow-auto px-4 py-3 text-sm text-gray-900 dark:text-gray-100 focus:outline-none [&_a]:text-accent [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:font-semibold [&_blockquote]:border-l-4 [&_blockquote]:border-gray-300 [&_blockquote]:pl-3 [&_blockquote]:text-gray-500 [&_img]:max-w-full [&_hr]:my-2">{!! $html !!}</div>
  <input type="hidden" name="{{ $name }}" x-ref="hidden">

  {{-- Link modal --}}
  <div x-show="linkOpen" x-cloak class="fixed inset-0 z-[1200] flex items-center justify-center p-4" @keydown.escape.window="linkOpen = false">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="linkOpen = false"></div>
    <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.wysiwyg_link_title') }}</h3>
      <label class="mt-3 block text-xs text-gray-600 dark:text-gray-400">{{ __('settings.wysiwyg_link_text') }}
        <input type="text" x-model="linkText" class="mt-1 {{ $inp }}">
      </label>
      <label class="mt-2 block text-xs text-gray-600 dark:text-gray-400">{{ __('settings.wysiwyg_link_url') }}
        <input type="url" x-model="linkUrl" placeholder="https://…" @keydown.enter.prevent="applyLink()" class="mt-1 {{ $inp }}">
      </label>
      <div class="mt-4 flex justify-end gap-2">
        <x-button variant="secondary" size="sm" type="button" @click="linkOpen = false">{{ __('common.cancel') }}</x-button>
        <x-button variant="primary" size="sm" type="button" ::disabled="! /^(https?:|mailto:)/i.test(linkUrl.trim())" @click="applyLink()">{{ __('settings.wysiwyg_insert') }}</x-button>
      </div>
    </div>
  </div>

  {{-- Image modal --}}
  <div x-show="imgOpen" x-cloak class="fixed inset-0 z-[1200] flex items-center justify-center p-4" @keydown.escape.window="imgOpen = false">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="imgOpen = false"></div>
    <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl">
      <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.wysiwyg_image_title') }}</h3>
      <div class="mt-3 flex gap-1 rounded-lg bg-black/[0.04] dark:bg-white/[0.06] p-0.5 text-xs">
        @foreach (['url' => 'wysiwyg_img_url', 'gallery' => 'wysiwyg_img_gallery', 'upload' => 'wysiwyg_img_upload'] as $t => $lk)
          <button type="button" @click="imgTab = '{{ $t }}'; if ('{{ $t }}' === 'gallery') loadGallery()" class="flex-1 rounded-md px-2 py-1 font-medium transition" :class="imgTab === '{{ $t }}' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-600 dark:text-gray-400'">{{ __('settings.' . $lk) }}</button>
        @endforeach
      </div>
      <div class="mt-3 flex-1 overflow-auto">
        <div x-show="imgTab === 'url'">
          <input type="url" x-model="imgUrl" placeholder="https://…/image.png" @keydown.enter.prevent="insertImageUrl()" class="{{ $inp }}">
        </div>
        <div x-show="imgTab === 'upload'" class="text-center">
          <label class="inline-flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 px-6 py-8 hover:border-accent">
            <x-icon name="arrow-up-tray" class="h-6 w-6 text-gray-400" />
            <span class="text-sm text-gray-600 dark:text-gray-300" x-text="imgBusy ? @js(__('settings.wysiwyg_img_uploading')) : @js(__('settings.wysiwyg_img_choose'))"></span>
            <input type="file" accept="image/*" class="sr-only" @change="uploadImage($event)">
          </label>
          <p class="mt-2 text-xs text-gray-400">{{ __('settings.wysiwyg_img_upload_hint') }}</p>
        </div>
        <div x-show="imgTab === 'gallery'">
          <div x-show="imgBusy && ! imgGallery.length" class="py-8 text-center text-sm text-gray-400"><x-icon name="arrow-path" class="mx-auto h-5 w-5 animate-spin" /></div>
          <template x-if="! imgBusy && imgGalleryLoaded && ! imgGallery.length"><p class="py-8 text-center text-sm text-gray-400">{{ __('settings.wysiwyg_img_gallery_empty') }}</p></template>
          <div class="grid grid-cols-4 gap-1.5">
            <template x-for="it in imgGallery" :key="it.id">
              <button type="button" @click="pickGallery(it)" class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800 hover:ring-2 hover:ring-accent">
                <img x-show="it.thumb" :src="it.thumb" class="h-full w-full object-cover">
              </button>
            </template>
          </div>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-2">
        <x-button variant="secondary" size="sm" type="button" @click="imgOpen = false">{{ __('common.cancel') }}</x-button>
        <x-button variant="primary" size="sm" type="button" x-show="imgTab === 'url'" ::disabled="! /^https?:\/\//i.test(imgUrl.trim())" @click="insertImageUrl()">{{ __('settings.wysiwyg_insert') }}</x-button>
      </div>
    </div>
  </div>
</div>
