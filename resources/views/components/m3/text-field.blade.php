@props(['label' => '', 'id' => null, 'type' => 'text', 'textarea' => false, 'leadingIcon' => null])

@php
    $id = $id ?? 'f'.substr(md5($label.$type), 0, 6);
    $field = 'peer w-full rounded-lg border border-md-outline bg-transparent text-sm text-md-on-surface outline-none transition focus:border-md-primary focus:ring-1 focus:ring-md-primary '
        .($leadingIcon ? 'pl-10 ' : 'pl-3 ').'pr-3 pt-5 pb-2';
@endphp

<div class="relative">
    @if ($leadingIcon)
        <x-m3.icon :name="$leadingIcon" class="pointer-events-none absolute left-3 top-3.5 text-lg text-md-on-surface-var" />
    @endif
    @if ($textarea)
        <textarea id="{{ $id }}" placeholder=" " {{ $attributes->class($field) }} rows="3"></textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" placeholder=" " {{ $attributes->class($field) }}>
    @endif
    <label for="{{ $id }}"
        class="pointer-events-none absolute {{ $leadingIcon ? 'left-10' : 'left-3' }} top-1.5 text-xs text-md-on-surface-var transition-all
               peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-sm
               peer-focus:top-1.5 peer-focus:text-xs peer-focus:text-md-primary">{{ $label }}</label>
</div>
