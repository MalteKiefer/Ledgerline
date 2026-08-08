@props(['variant' => 'outlined'])

@php
    $v = [
        'outlined' => 'border border-md-outline-variant',
        'elevated' => 'border border-transparent shadow-md',
    ][$variant] ?? 'border border-md-outline-variant';
@endphp

<div {{ $attributes->class('rounded-xl bg-md-surface '.$v) }}>{{ $slot }}</div>
