<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ auth()->check() ? '' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- App version for the SPA sidebar footer. The SPA authenticates via a
         client-side bearer token (localStorage), NOT a web session, so the blade
         cannot see whether the visitor is signed in — an `@auth` gate here is
         always false for real SPA users and left the footer showing a bare "v".
         The version is a repo-public build number (README, git tags, openapi),
         and it is only ever DISPLAYED inside the sidebar, which the SPA renders
         only for authenticated users. --}}
    <meta name="ll-version" content="{{ config('app.version') }}">
    <title>Ledgerline</title>
    @vite(['resources/js/spa/main.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
