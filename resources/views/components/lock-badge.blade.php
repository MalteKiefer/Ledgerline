@props(['encrypted' => false])

@php
    $lockClosed = 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z';
    $lockOpen = 'M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 21.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z';
@endphp

@if ($encrypted)
    {{-- Encrypted file: closed padlock while the vault is locked, open once it is
         unlocked (the content is then readable). Always signals "encrypted". --}}
    <span x-data="{}" class="inline-flex shrink-0 text-gray-500" title="{{ __('files.encrypted') }}">
        <svg x-show="! $store.vault.unlocked" {{ $attributes->merge(['class' => 'h-4 w-4']) }} fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $lockClosed }}" />
        </svg>
        <svg x-show="$store.vault.unlocked" x-cloak {{ $attributes->merge(['class' => 'h-4 w-4']) }} fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $lockOpen }}" />
        </svg>
    </span>
@else
    {{-- Not encrypted: a faint open padlock. --}}
    <svg {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0 text-gray-300']) }} fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true" title="{{ __('files.not_encrypted') }}">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $lockOpen }}" />
    </svg>
@endif
