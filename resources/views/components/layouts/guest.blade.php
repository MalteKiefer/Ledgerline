@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Ledgerline</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-md-surface-2 dark:bg-md-surface-2 text-md-on-surface dark:text-md-on-surface antialiased">
    <main class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
        <p class="mt-6 text-center text-xs text-md-on-surface-var dark:text-md-on-surface-var">Ledgerline v{{ config('app.version') }}</p>
    </main>
</body>
</html>
