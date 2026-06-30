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
<body class="h-full bg-gray-100 text-gray-900 antialiased">
    <div class="min-h-full">
        <header class="border-b border-gray-200 bg-white">
            <nav class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-900">
                        Ledgerline
                    </a>
                    @auth
                        <div class="flex items-center gap-4 text-sm">
                            <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                            <a href="{{ route('customers.index') }}" class="text-gray-600 hover:text-gray-900">Customers</a>
                            <a href="{{ route('projects.overview') }}" class="text-gray-600 hover:text-gray-900">Projects</a>
                        </div>
                    @endauth
                </div>

                @auth
                    <div class="flex items-center gap-4">
                        <x-spotlight-search />
                        <a href="{{ route('profile') }}"
                            class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                            <x-user-avatar :user="auth()->user()" size="h-8 w-8" />
                            <span>{{ auth()->user()->name }}</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Log out
                            </button>
                        </form>
                    </div>
                @endauth
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    role="status">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
