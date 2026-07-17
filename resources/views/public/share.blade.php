<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('gallery.share_page_title') }} — Ledgerline</title>
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
  <div x-data="publicShare({
        metaUrl: '{{ route('public.share.meta', $token) }}',
        unlockUrl: '{{ route('public.share.unlock', $token) }}',
        manifestUrl: '{{ route('public.share.manifest', $token) }}',
        blobBase: '{{ url('/s/'.$token.'/blob') }}',
     }, {
        noKey: @js(__('gallery.share_err_no_key')),
        badKey: @js(__('gallery.share_err_bad_key')),
        wrongPassword: @js(__('gallery.share_err_wrong_password')),
     })" x-cloak>

    <header class="border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <span class="text-sm font-semibold tracking-tight">Ledgerline</span>
        <span x-show="state === 'ready' && manifest?.name" x-cloak class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="manifest?.name"></span>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
      {{-- Loading --}}
      <div x-show="state === 'boot'" class="flex items-center justify-center py-24">
        <svg class="h-6 w-6 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
      </div>

      {{-- Not found / expired / error --}}
      <template x-if="state === 'notfound' || state === 'expired' || state === 'error'">
        <div class="mx-auto mt-16 flex max-w-md flex-col items-center rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
          <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="lock-closed" class="h-7 w-7 text-gray-400" /></div>
          <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"
             x-text="state === 'expired' ? @js(__('gallery.share_expired')) : (state === 'notfound' ? @js(__('gallery.share_not_found')) : (error || @js(__('gallery.share_error'))))"></p>
        </div>
      </template>

      {{-- Password gate --}}
      <template x-if="state === 'password'">
        <form @submit.prevent="unlock()" class="mx-auto mt-16 flex max-w-sm flex-col items-center rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center shadow-sm">
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="lock-closed" class="h-6 w-6 text-gray-400" /></div>
          <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">{{ __('gallery.share_password_prompt') }}</p>
          <input type="password" x-model="password" autocomplete="current-password" class="mt-4 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500">
          <p x-show="error" x-cloak class="mt-2 text-xs text-red-600 dark:text-red-400" x-text="error"></p>
          <button type="submit" :disabled="unlocking || ! password" class="mt-4 w-full rounded-lg bg-gray-900 dark:bg-gray-100 px-4 py-2.5 text-sm font-medium text-white dark:text-gray-900 disabled:opacity-50">{{ __('gallery.share_unlock') }}</button>
        </form>
      </template>

      {{-- Album grid --}}
      <div x-show="state === 'ready'" x-cloak>
        <template x-if="! photos.length"><p class="mt-16 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.album_empty') }}</p></template>
        <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 lg:grid-cols-6">
          <template x-for="p in photos" :key="p.id">
            <div class="group relative aspect-square overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800" x-intersect.once="thumbFor(p)">
              <button type="button" @click="openViewer(p)" class="block h-full w-full">
                <img x-show="thumbs[p.id]" :src="thumbs[p.id]" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                <div x-show="!thumbs[p.id]" class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900">
                  <svg class="h-5 w-5 animate-spin text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                </div>
                <template x-if="p.t === 'video'"><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm"><x-icon name="play" class="h-5 w-5" /></span></span></template>
              </button>
            </div>
          </template>
        </div>
      </div>
    </main>

    {{-- Viewer --}}
    <div x-show="viewer.open" x-cloak @keydown.escape.window="closeViewer()" class="fixed inset-0 z-[950] flex bg-black/90" @click.self="closeViewer()">
      <button type="button" @click="closeViewer()" class="absolute right-4 top-4 z-10 text-white/70 hover:text-white"><x-icon name="x-mark" class="h-7 w-7" /></button>
      <button type="button" x-show="canDownload(viewer.photo)" x-cloak @click="download(viewer.photo)" title="{{ __('gallery.share_download') }}" class="absolute right-16 top-4 z-10 text-white/70 hover:text-white"><x-icon name="arrow-down-tray" class="h-6 w-6" /></button>
      <div class="flex flex-1 items-center justify-center p-4" @click.self="closeViewer()">
        <template x-if="viewer.kind === 'image'"><img :src="viewer.src" class="max-h-full max-w-full object-contain"></template>
        <template x-if="viewer.kind === 'video'"><video :src="viewer.src" controls autoplay playsinline class="max-h-full max-w-full"></video></template>
      </div>
    </div>
  </div>
</body>
</html>
