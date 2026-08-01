{{-- Category autocomplete input bound to a JS lvalue.
     $model  = a JS expression usable as both getter and assignment target
               (e.g. "receiptDoc.r.category" or "partnerEditing.category").
     $commit = optional JS to run after a pick/blur (e.g. "saveReceiptDoc()").
     $placeholder = translated placeholder string. --}}
@php $commit = $commit ?? ''; @endphp
<div x-data="{ open: false }" class="relative" @click.outside="open = false">
  <div class="relative">
    <input type="text" x-model="{{ $model }}" @focus="open = true" @input="open = true"
           @keydown.escape.stop="open = false" placeholder="{{ $placeholder ?? '' }}"
           class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] text-sm pr-8">
    <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400"><x-icon name="chevron-down" class="h-4 w-4" /></span>
  </div>
  <div x-show="open" x-cloak x-transition.opacity
       class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-1 shadow-xl">
    <template x-for="name in catFilter({{ $model }})" :key="name">
      <button type="button" @click="{{ $model }} = name; open = false; {{ $commit }}"
              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm hover:bg-accent/5">
        <span class="ll-chip h-6 w-6 rounded-md shrink-0" :style="'--chip:'+catColor(name)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="catIconPath(catIcon(name))"></path></svg>
        </span>
        <span class="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200" x-text="name"></span>
      </button>
    </template>
    <template x-if="! catFilter({{ $model }}).length">
      <p class="px-2.5 py-2 text-xs text-gray-400">{{ __('invoices.cats_none') }}</p>
    </template>
  </div>
</div>
