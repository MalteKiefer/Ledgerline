<x-layouts.app :title="__('pages.dashboard.title')">
    <h1 class="text-2xl font-semibold text-gray-900">{{ __('pages.dashboard.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('pages.dashboard.subtitle') }}</p>

    <div class="mt-6 grid grid-cols-3 gap-4">
        <a href="{{ route('gallery.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.gallery_images') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $gallery['images'] }}</dd>
        </a>
        <a href="{{ route('gallery.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.gallery_videos') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $gallery['videos'] }}</dd>
        </a>
        <a href="{{ route('gallery.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.gallery_motion') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $gallery['motion'] }}</dd>
        </a>
    </div>

    {{-- The file vault is zero-knowledge: the server cannot count or list files,
         so the dashboard shows only a door into it. --}}
    <a href="{{ route('files.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
        <div>
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.files') }}</dt>
            <dd class="mt-2 text-base text-gray-900">
                {{ $vaultConfigured ? __('pages.dashboard.vault_ready') : __('pages.dashboard.vault_unconfigured') }}
            </dd>
        </div>
        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
    </a>

    <a href="{{ route('notes.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
        <div>
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.notes') }}</dt>
            <dd class="mt-2 text-base text-gray-900">
                {{ $vaultConfigured ? __('pages.dashboard.notes_ready') : __('pages.dashboard.notes_unconfigured') }}
            </dd>
        </div>
        <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
        </svg>
    </a>
</x-layouts.app>
