@props(['user', 'size' => 'h-8 w-8'])

{{--
    Renders the user's avatar, which was downloaded from Pocket-ID at login and
    is served same-origin through the authenticated avatar route. Falls back to
    the user's initial when no avatar is stored or the image fails to load (the
    Alpine @error handler covers stale references). No third-party request is
    made at render time.
--}}
@php
    $initial = strtoupper(mb_substr($user->name ?? '?', 0, 1));
    $initialClasses = $size.' inline-flex items-center justify-center rounded-full bg-gray-300 text-sm font-medium text-gray-700';
@endphp

@if (! empty($user->avatar))
    <span x-data="{ broken: false }" class="inline-flex">
        <img src="{{ route('profile.avatar') }}" alt="{{ $user->name }}" x-on:error="broken = true" x-show="! broken"
            {{ $attributes->merge(['class' => $size.' rounded-full bg-gray-200 object-cover']) }}>
        <span x-show="broken" x-cloak aria-hidden="true" class="{{ $initialClasses }}">{{ $initial }}</span>
    </span>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => $initialClasses]) }}>{{ $initial }}</span>
@endif
