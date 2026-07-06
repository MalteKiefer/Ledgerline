@props(['title' => 'Shared note'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — Ledgerline</title>
    {{-- Public share pages are static: styles only, no application JS, so the
         page can run under a strict script-less Content-Security-Policy. --}}
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 antialiased">
    <main class="min-h-full">
        {{ $slot }}
    </main>
</body>
</html>
