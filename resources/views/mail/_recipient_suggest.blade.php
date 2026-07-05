{{--
    Autocomplete dropdown for a recipient field (to / cc / bcc). Driven by the
    surrounding compose scope's `recipientBox` state, which is keyed by field so
    only the active field shows a list. Picking an entry inserts the bare email
    address into the comma-separated token being typed.

    Expects a `$field` variable ('to' | 'cc' | 'bcc').
--}}
<div x-show="recipientBox.field === '{{ $field }}' && recipientBox.items.length" x-cloak
    class="absolute left-0 right-0 top-full z-10 mt-1 max-h-60 overflow-auto rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg"
    role="listbox" aria-label="{{ __('mail.recipient_search_label') }}">
    <template x-for="(r, i) in recipientBox.items" :key="r.email + i">
        <button type="button"
            @mousedown.prevent="recipientPick('{{ $field }}', r)"
            @mouseenter="recipientBox.active = i"
            class="flex w-full flex-col items-start gap-0.5 px-3 py-1.5 text-left hover:bg-gray-50"
            :class="recipientBox.active === i ? 'bg-gray-50' : ''"
            role="option" :aria-selected="recipientBox.active === i">
            <span class="truncate font-medium text-gray-900" x-text="r.name || r.email"></span>
            <span class="truncate text-xs text-gray-500" x-show="r.name" x-text="r.email"></span>
        </button>
    </template>
</div>
