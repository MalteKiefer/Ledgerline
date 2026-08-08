{{-- Action/breadcrumb bar above a list (Roundcube-style). --}}
<div {{ $attributes->class('flex items-center gap-2 px-3 py-2 min-h-13 border-b border-md-outline-variant text-md-on-surface-var') }}>
    {{ $slot }}
</div>
