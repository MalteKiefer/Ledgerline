{{-- Category badge: a color-tinted pill with the category's icon + name.
     $name = a JS expression that evaluates to the category name string.
     Renders nothing when the name is empty. --}}
<template x-if="{{ $name }}">
  <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium"
        :style="'background:'+catColor({{ $name }})+'1f; color:'+catColor({{ $name }})">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-3.5 w-3.5 shrink-0" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" :d="catIconPath(catIcon({{ $name }}))"></path>
    </svg>
    <span x-text="{{ $name }}"></span>
  </span>
</template>
