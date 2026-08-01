{{-- Multi-category picker for a receipt (a "Fall" can have several categories).
     $model  = JS expression for the receipt object holding `categories[]`
               (e.g. "receiptDoc.r").
     $commit = JS run after add/remove (e.g. "saveReceiptDoc()"). --}}
@php $commit = $commit ?? ''; @endphp
<div x-data="{ open: false, q: '' }" class="relative" @click.outside="open = false">
  <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-[#2c2c2e] px-2 py-1.5 focus-within:border-accent focus-within:ring-1 focus-within:ring-accent">
    <template x-for="cat in catList({{ $model }})" :key="cat">
      <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium" :style="'background:'+catColor(cat)+'1f; color:'+catColor(cat)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-3 w-3 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="catIconPath(catIcon(cat))"></path></svg>
        <span x-text="cat"></span>
        <button type="button" @click="removeReceiptCat({{ $model }}, cat, () => { {{ $commit }} })" class="ml-0.5 leading-none opacity-70 hover:opacity-100" aria-label="×">&times;</button>
      </span>
    </template>
    <input type="text" x-model="q" @focus="open = true" @input="open = true"
           @keydown.enter.prevent="if (q.trim()) { addReceiptCat({{ $model }}, q.trim(), () => { {{ $commit }} }); q = ''; open = false }"
           @keydown.escape.stop="open = false"
           placeholder="{{ __('invoices.receipt_category_ph') }}"
           class="min-w-[7rem] flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
  </div>
  <div x-show="open" x-cloak x-transition.opacity
       class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-1 shadow-xl">
    <template x-for="name in catFilter(q).filter((n) => ! catList({{ $model }}).some((c) => c.toLowerCase() === n.toLowerCase()))" :key="name">
      <button type="button" @click="addReceiptCat({{ $model }}, name, () => { {{ $commit }} }); q = ''; open = false"
              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm hover:bg-accent/5">
        <span class="ll-chip h-6 w-6 rounded-md shrink-0" :style="'--chip:'+catColor(name)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="catIconPath(catIcon(name))"></path></svg></span>
        <span class="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200" x-text="name"></span>
      </button>
    </template>
    <template x-if="q.trim() && ! catFilter(q).some((n) => n.toLowerCase() === q.trim().toLowerCase())">
      <button type="button" @click="addReceiptCat({{ $model }}, q.trim(), () => { {{ $commit }} }); q = ''; open = false"
              class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-sm text-accent hover:bg-accent/5">
        <x-icon name="plus" class="h-4 w-4" /> <span x-text="q"></span>
      </button>
    </template>
  </div>
</div>
