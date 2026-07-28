{{-- A 3-dot (ellipsis) actions dropdown. Put multi-action button clusters in here for a
     consistent, compact header across every module. Slot holds <x-action-menu-item> rows
     (or <a>/<button>); clicking any item closes the menu (the container catches the bubble).

     Usage:
       <x-action-menu :aria-label="__('common.actions')">
         <x-action-menu-item icon="pencil" @click="rename()">{{ __('...') }}</x-action-menu-item>
         <x-action-menu-item icon="trash" danger @click="remove()">{{ __('common.delete') }}</x-action-menu-item>
       </x-action-menu>
--}}
@props(['align' => 'right', 'icon' => 'ellipsis', 'width' => 'w-52'])
<div class="relative shrink-0" x-data="{ open: false }" @keydown.escape.stop="open = false">
    <x-icon-button :name="$icon" tone="gray" size="sm" @click="open = ! open" ::aria-expanded="open"
        {{ $attributes->only('aria-label') }} />
    <div x-show="open" x-cloak @click.outside="open = false" @click="open = false"
        class="absolute {{ $align === 'left' ? 'left-0' : 'right-0' }} z-30 mt-1 {{ $width }} overflow-hidden rounded-xl border border-black/[0.08] dark:border-white/10 bg-white dark:bg-[#1c1c1e] py-1 shadow-xl">
        {{ $slot }}
    </div>
</div>
