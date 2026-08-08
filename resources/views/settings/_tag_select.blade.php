{{-- Autocomplete multi-select. Params: $fieldName (posts as name[]), $options
     (array of ['id'=>,'label'=>,'sub'=>?]), $selected (array of ids), $placeholder. --}}
@php $ts = ['options' => array_values($options), 'selected' => array_values($selected), 'name' => $fieldName.'[]']; @endphp
<div x-data="tagSelect(@js($ts))" @click.outside="open = false" class="relative">
    <div class="flex flex-wrap items-center gap-1.5 rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface px-2 py-1.5 focus-within:border-accent focus-within:ring-1 focus-within:ring-accent">
        <template x-for="o in chosen" :key="o.id">
            <span class="inline-flex items-center gap-1 rounded-md bg-accent/10 px-2 py-0.5 text-xs font-medium text-accent">
                <span x-text="o.label"></span>
                <button type="button" @click="remove(o.id)" class="text-accent/70 hover:text-accent">&times;</button>
            </span>
        </template>
        <input type="text" x-model="q" @focus="open = true" @input="open = true"
            placeholder="{{ $placeholder ?? __('settings.tagselect_placeholder') }}"
            class="min-w-[8rem] flex-1 border-0 bg-transparent p-0.5 text-sm text-md-on-surface dark:text-md-on-surface focus:outline-none focus:ring-0">
    </div>
    <template x-for="id in selected" :key="'h'+id"><input type="hidden" :name="name" :value="id"></template>
    <div x-show="open && matches.length" x-cloak class="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-lg border border-md-outline-variant dark:border-md-outline-variant bg-white dark:bg-md-surface py-1 shadow-xl">
        <template x-for="o in matches" :key="o.id">
            <button type="button" @click="add(o.id)" class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent/5">
                <span class="truncate text-md-on-surface dark:text-md-on-surface" x-text="o.label"></span>
                <span class="truncate text-xs text-md-on-surface-var dark:text-md-on-surface-var" x-text="o.sub"></span>
            </button>
        </template>
    </div>
</div>
