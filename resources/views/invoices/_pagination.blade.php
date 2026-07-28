{{-- Reusable finance-table pagination footer. Params are Alpine expression/method names
     on the invoices component: page (prop), pageCount (getter), setPerPage + goto
     (methods). Page-size options come from `perPageOptions`. --}}
@props(['page', 'perPage', 'pageCount', 'setPerPage', 'goto'])
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-black/[0.06] dark:border-white/10 px-4 py-2.5 text-sm">
  <div class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
    <span>{{ __('invoices.per_page') }}</span>
    <template x-for="n in perPageOptions" :key="n">
      <button type="button" @click="{{ $setPerPage }}(n)" class="rounded-md px-2 py-1 font-medium transition-colors"
        :class="{{ $perPage }} === n ? 'bg-accent/10 text-accent' : 'text-gray-500 hover:text-accent dark:text-gray-400'" x-text="n"></button>
    </template>
  </div>
  <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
    <span x-text="'{{ __('invoices.page_of') }}'.replace(':p', {{ $page }}).replace(':n', {{ $pageCount }})"></span>
    <x-icon-button name="chevron-left" tone="gray" size="sm" ::disabled="{{ $page }} <= 1" @click="{{ $goto }}({{ $page }} - 1)" aria-label="prev" />
    <x-icon-button name="chevron-right" tone="gray" size="sm" ::disabled="{{ $page }} >= {{ $pageCount }}" @click="{{ $goto }}({{ $page }} + 1)" aria-label="next" />
  </div>
</div>
