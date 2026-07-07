@props(['title' => 'Ledgerline'])
<!DOCTYPE html>
@php $llTheme = auth()->check() ? (\App\Models\UserSetting::for(auth()->id())->theme ?? 'system') : 'system'; @endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-theme="{{ $llTheme }}">
<head>
    <meta charset="utf-8">
    <script>{!! \App\Support\ThemeBootstrap::SCRIPT !!}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Ledgerline</title>
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-100 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100" x-data>
    <div class="mx-auto flex min-h-full max-w-lg flex-col justify-center px-4 py-10">
        {{ $slot }}
        <p class="mt-8 text-center text-xs text-gray-400 dark:text-gray-600">Ledgerline</p>
    </div>
</body>
</html>
