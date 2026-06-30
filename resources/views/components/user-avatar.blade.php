@props(['user', 'size' => 'h-8 w-8'])

{{--
    Renders the user's Pocket-ID avatar, falling back to their initial when no
    avatar URL is present or the image fails to load. The avatar is served by
    the user's own Pocket-ID instance (first-party identity data); no third-
    party tracker or CDN is involved. referrerpolicy keeps the app URL private.
--}}
@php
    $initial = strtoupper(mb_substr($user->name ?? '?', 0, 1));
@endphp

@if (! empty($user->avatar))
    <img src="{{ $user->avatar }}" alt="{{ $user->name }}" referrerpolicy="no-referrer"
        {{ $attributes->merge(['class' => $size.' rounded-full bg-gray-200 object-cover']) }}>
@else
    <span aria-hidden="true"
        {{ $attributes->merge(['class' => $size.' inline-flex items-center justify-center rounded-full bg-gray-300 text-sm font-medium text-gray-700']) }}>
        {{ $initial }}
    </span>
@endif
