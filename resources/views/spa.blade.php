<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ auth()->check() ? '' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="ll-version" content="{{ config('app.version') }}">
    <title>Ledgerline</title>
    @vite(['resources/js/spa/main.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
