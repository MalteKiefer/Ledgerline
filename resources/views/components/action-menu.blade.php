{{-- A 3-dot (ellipsis) actions dropdown. Put multi-action button clusters in here for a
     consistent, compact header across every module. Slot holds <x-action-menu-item> rows
     (or <a>/<button>); clicking any item closes the menu (the container catches the bubble).

     The panel is TELEPORTED to <body> and positioned `fixed` at the trigger, so it is never
     clipped by an ancestor with `overflow-hidden`/`overflow-x-auto` (e.g. a scrollable list
     card — the last rows' menus used to get cut off at the table's bottom edge). Closes on
     outside-click and on window scroll (the fixed panel doesn't follow the trigger).

     Usage:
       <x-action-menu :aria-label="__('common.actions')">
         <x-action-menu-item icon="pencil" @click="rename()">{{ __('...') }}</x-action-menu-item>
         <x-action-menu-item icon="trash" danger @click="remove()">{{ __('common.delete') }}</x-action-menu-item>
       </x-action-menu>
--}}
@props(['align' => 'right', 'icon' => 'ellipsis', 'width' => 'w-52'])
<div class="shrink-0"
     x-data="{ open: false, _x: 0, _y: 0,
        _place() {
            const r = $refs.amTrigger.getBoundingClientRect();
            this._x = {{ $align === 'left' ? 'r.left' : 'r.right' }};
            this._y = r.bottom + 4;
            // Flip the panel above the trigger when it would overflow the viewport
            // bottom (e.g. the last rows of a scrollable table) so it never lands
            // behind the scrollbar; otherwise clamp it into view.
            this.$nextTick(() => {
                const h = $refs.amPanel?.offsetHeight || 0;
                if (r.bottom + 4 + h > window.innerHeight - 8) {
                    this._y = r.top - h - 4 > 8 ? r.top - h - 4 : Math.max(8, window.innerHeight - 8 - h);
                }
            });
        },
        _toggle() { this.open = ! this.open; if (this.open) this.$nextTick(() => this._place()); } }"
     @keydown.escape.stop="open = false">
    <x-icon-button x-ref="amTrigger" :name="$icon" tone="gray" size="sm" @click="_toggle()" ::aria-expanded="open"
        {{ $attributes->only('aria-label') }} />
    <template x-teleport="body">
        <div x-show="open" x-cloak x-ref="amPanel"
             @click.outside="if (! $refs.amTrigger?.contains($event.target)) open = false"
             @click="open = false"
             @scroll.window="open = false"
             class="fixed z-[1600] {{ $width }} max-h-[80vh] overflow-y-auto rounded-xl border border-black/[0.08] dark:border-white/10 bg-white dark:bg-md-surface py-1 shadow-xl"
             :style="`top: ${_y}px; left: ${_x}px;{{ $align === 'left' ? '' : ' transform: translateX(-100%);' }}`">
            {{ $slot }}
        </div>
    </template>
</div>
