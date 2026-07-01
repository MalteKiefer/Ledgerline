<x-layouts.app :title="__('pages.dashboard.title')">
    @php
        $formatBytes = static function (int $bytes): string {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = 0;
            $value = (float) $bytes;
            while ($value >= 1024 && $i < count($units) - 1) {
                $value /= 1024;
                $i++;
            }

            return number_format($value, $i > 0 ? 1 : 0).' '.$units[$i];
        };
    @endphp

    <h1 class="text-2xl font-semibold text-gray-900">{{ __('pages.dashboard.heading') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('pages.dashboard.subtitle') }}</p>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('customers.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.customers') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['customers'] }}</dd>
        </a>
        <a href="{{ route('projects.overview') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.projects') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['projects'] }}</dd>
        </a>
        <a href="{{ route('files.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.files') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['files'] }}</dd>
        </a>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <dt class="text-sm font-medium text-gray-500">{{ __('pages.dashboard.storage_used') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $formatBytes($stats['storage']) }}</dd>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-4">
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

    <section class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('pages.dashboard.recent_files') }}</h2>
            <a href="{{ route('files.index') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('pages.dashboard.view_all') }}</a>
        </div>
        <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($recentFiles->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-gray-500">{{ __('pages.dashboard.no_files') }}</p>
            @else
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($recentFiles as $file)
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="min-w-0">
                                <a href="{{ route('files.show', $file) }}"
                                    class="font-medium text-gray-900 hover:underline">{{ $file->displayTitle }}</a>
                                <span class="text-gray-500">
                                    — {{ $file->type->label() }}@if ($file->attachable) · {{ $file->attachable->name }}@endif
                                </span>
                            </span>
                            <span class="shrink-0 text-gray-500">{{ $formatBytes($file->size) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layouts.app>
