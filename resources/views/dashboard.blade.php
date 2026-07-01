<x-layouts.app title="Dashboard">
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

    <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
    <p class="mt-1 text-sm text-gray-600">Overview of your ERP records.</p>

    <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('customers.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">Customers</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['customers'] }}</dd>
        </a>
        <a href="{{ route('projects.overview') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">Projects</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['projects'] }}</dd>
        </a>
        <a href="{{ route('files.index') }}" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm hover:border-gray-300">
            <dt class="text-sm font-medium text-gray-500">Files</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $stats['files'] }}</dd>
        </a>
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <dt class="text-sm font-medium text-gray-500">Storage used</dt>
            <dd class="mt-2 text-3xl font-semibold text-gray-900">{{ $formatBytes($stats['storage']) }}</dd>
        </div>
    </div>

    <section class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Recent files</h2>
            <a href="{{ route('files.index') }}" class="text-sm text-gray-600 hover:text-gray-900">View all</a>
        </div>
        <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            @if ($recentFiles->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-gray-500">No files yet.</p>
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
