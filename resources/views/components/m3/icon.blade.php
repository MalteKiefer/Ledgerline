@props(['name' => '', 'fill' => false])

{{-- Material Symbols Outlined glyph. Size via a text-* class, e.g.
     <x-m3.icon name="search" class="text-xl" />. `fill` uses the filled axis. --}}
<span {{ $attributes->class(['msym', 'msym-fill' => $fill]) }} aria-hidden="true">{{ $name }}</span>
