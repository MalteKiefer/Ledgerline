<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('files.share_page_title') }} — Ledgerline</title>
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-100 antialiased">
  <div x-data="fileShare({
        metaUrl: '{{ route('public.share.meta', $token) }}',
        unlockUrl: '{{ route('public.share.unlock', $token) }}',
        manifestUrl: '{{ route('public.share.manifest', $token) }}',
        blobBase: '{{ url('/s/'.$token.'/blob') }}',
     }, {
        noKey: @js(__('files.share_err_no_key')),
        badKey: @js(__('files.share_err_bad_key')),
        wrongPassword: @js(__('files.share_err_wrong_password')),
     })" x-cloak>

    <header class="border-b border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e]">
      <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
        <span class="text-sm font-semibold tracking-tight">Ledgerline</span>
        <span x-show="state === 'ready' && manifest?.name" x-cloak class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="manifest?.name"></span>
      </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8">
      <div x-show="state === 'boot'" class="flex items-center justify-center py-24">
        <svg class="h-6 w-6 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
      </div>

      <template x-if="state === 'notfound' || state === 'expired' || state === 'error'">
        <div class="mx-auto mt-16 flex max-w-md flex-col items-center rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-12 text-center">
          <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="lock-closed" class="h-7 w-7 text-gray-400" /></div>
          <p class="mt-4 text-sm text-gray-600 dark:text-gray-300"
             x-text="state === 'expired' ? @js(__('gallery.share_expired')) : (state === 'notfound' ? @js(__('gallery.share_not_found')) : (error || @js(__('files.share_error'))))"></p>
        </div>
      </template>

      <template x-if="state === 'password'">
        <form @submit.prevent="unlock()" class="mx-auto mt-16 flex max-w-sm flex-col items-center rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-8 text-center shadow-sm">
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800"><x-icon name="lock-closed" class="h-6 w-6 text-gray-400" /></div>
          <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">{{ __('gallery.share_password_prompt') }}</p>
          <input type="password" x-model="password" autocomplete="current-password" class="mt-4 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-accent focus:ring-accent">
          <p x-show="error" x-cloak class="mt-2 text-xs text-red-600 dark:text-red-400" x-text="error"></p>
          <button type="submit" :disabled="unlocking || ! password" class="mt-4 w-full ll-accent rounded-xl px-4 py-2.5 text-sm font-medium disabled:opacity-50">{{ __('gallery.share_unlock') }}</button>
        </form>
      </template>

      {{-- Single file or a browsable folder tree (structure preserved) --}}
      <div x-show="state === 'ready'" x-cloak>
        {{-- Breadcrumb (folder shares only) --}}
        <nav x-show="isFolder" class="mb-3 flex flex-wrap items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
          <button type="button" @click="goTo('')" class="rounded px-1.5 py-0.5 hover:bg-gray-200/60 dark:hover:bg-gray-800" :class="cwd === '' ? 'font-medium text-gray-900 dark:text-gray-100' : ''" x-text="manifest?.name || '/'"></button>
          <template x-for="(c, i) in crumbs" :key="c.path">
            <span class="flex items-center gap-1">
              <span class="text-gray-300 dark:text-gray-600">/</span>
              <button type="button" @click="goTo(c.path)" class="rounded px-1.5 py-0.5 hover:bg-gray-200/60 dark:hover:bg-gray-800" :class="i === crumbs.length - 1 ? 'font-medium text-gray-900 dark:text-gray-100' : ''" x-text="c.name"></button>
            </span>
          </template>
        </nav>

        <template x-if="! allFiles.length"><p class="mt-16 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('files.share_empty') }}</p></template>
        <template x-if="allFiles.length && ! subfolders.length && ! filesHere.length"><p class="mt-16 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('gallery.album_empty') }}</p></template>

        <ul x-show="subfolders.length || filesHere.length" class="divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e]">
          {{-- Subfolders first --}}
          <template x-for="name in subfolders" :key="'d:' + name">
            <li>
              <button type="button" @click="enterFolder(name)" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent/5">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white shadow-sm" style="background:#3b9fd6"><x-icon name="folder" class="h-5 w-5" /></span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="name"></span>
                  <span class="block text-xs text-gray-400" x-text="folderFileCount(name) + ' {{ __('files.share_items') }}'"></span>
                </span>
                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
              </button>
            </li>
          </template>
          {{-- Files in this folder --}}
          <template x-for="f in filesHere" :key="f.ref">
            <li class="flex items-center gap-3 px-4 py-3 hover:bg-accent/5">
              <button type="button" @click="open(f)" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800" x-init="isImage(f) && $nextTick(() => thumbFor(f))">
                  <img x-show="isImage(f) && thumbs[f.ref]" :src="thumbs[f.ref]" class="h-full w-full object-cover">
                  <x-icon x-show="! (isImage(f) && thumbs[f.ref])" name="document" class="h-5 w-5 text-gray-400" />
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="f.name"></span>
                  <span class="block truncate text-xs text-gray-400" x-text="fmtSize(f.size)"></span>
                </span>
              </button>
              <button type="button" @click="download(f)" title="{{ __('files.share_download') }}" class="shrink-0 rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200"><x-icon name="arrow-down-tray" class="h-5 w-5" /></button>
            </li>
          </template>
        </ul>
      </div>
    </main>

    {{-- Preview --}}
    <div x-show="viewer.open" x-cloak @keydown.escape.window="closeViewer()" class="fixed inset-0 z-[950] flex bg-black/90" @click.self="closeViewer()">
      <button type="button" @click="closeViewer()" class="absolute right-4 top-4 z-10 text-white/70 hover:text-white"><x-icon name="x-mark" class="h-7 w-7" /></button>
      <button type="button" x-show="viewer.file" @click="download(viewer.file)" title="{{ __('files.share_download') }}" class="absolute right-16 top-4 z-10 text-white/70 hover:text-white"><x-icon name="arrow-down-tray" class="h-6 w-6" /></button>
      <div class="flex flex-1 items-center justify-center p-4" @click.self="closeViewer()">
        <template x-if="viewer.kind === 'image'"><img :src="viewer.src" class="max-h-full max-w-full object-contain"></template>
        <template x-if="viewer.kind === 'pdf'"><iframe :src="viewer.src" class="h-full w-full rounded bg-white" title=""></iframe></template>
      </div>
    </div>
  </div>
</body>
</html>
