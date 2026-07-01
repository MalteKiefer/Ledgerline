<x-layouts.app :title="__('settings.gallery_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.gallery_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('settings.gallery_heading') }}</h1>

    <form method="POST" action="{{ route('settings.gallery.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        {{-- General (uploads) --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.general_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.general_hint') }}</p>
            <div class="mt-3">
                <label for="gallery_max_upload_mb" class="block text-sm font-medium text-gray-700">{{ __('settings.max_upload_mb') }}</label>
                <input type="number" min="1" max="5120" id="gallery_max_upload_mb" name="gallery_max_upload_mb" value="{{ old('gallery_max_upload_mb', $company->gallery_max_upload_mb ?? 200) }}" class="{{ $input }} sm:max-w-xs">
                <p class="mt-1 text-xs text-gray-500">{{ __('settings.max_upload_mb_hint') }}</p>
                @error('gallery_max_upload_mb')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="mt-4">
                <label for="gallery_filename_template" class="block text-sm font-medium text-gray-700">{{ __('settings.filename_template') }}</label>
                <input type="text" id="gallery_filename_template" name="gallery_filename_template" value="{{ old('gallery_filename_template', $company->gallery_filename_template) }}" placeholder="@{{y}}-@{{MM}}-@{{dd}}_@{{HH}}-@{{mm}}-@{{ss}}" class="{{ $input }} font-mono">
                <p class="mt-1 text-xs text-gray-500">{{ __('settings.filename_template_hint') }}</p>
                @error('gallery_filename_template')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Photos --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.photos_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.trips_hint') }}</p>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="gallery_trip_gap_days" class="block text-sm font-medium text-gray-700">{{ __('settings.trip_gap_days') }}</label>
                    <input type="number" min="1" max="60" id="gallery_trip_gap_days" name="gallery_trip_gap_days" value="{{ old('gallery_trip_gap_days', $company->gallery_trip_gap_days ?? 2) }}" class="{{ $input }}">
                    @error('gallery_trip_gap_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="gallery_trip_radius_km" class="block text-sm font-medium text-gray-700">{{ __('settings.trip_radius_km') }}</label>
                    <input type="number" min="1" max="5000" id="gallery_trip_radius_km" name="gallery_trip_radius_km" value="{{ old('gallery_trip_radius_km', $company->gallery_trip_radius_km ?? 100) }}" class="{{ $input }}">
                    @error('gallery_trip_radius_km')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="gallery_map_zoom" class="block text-sm font-medium text-gray-700">{{ __('settings.map_zoom') }}</label>
                    <input type="number" min="1" max="19" id="gallery_map_zoom" name="gallery_map_zoom" value="{{ old('gallery_map_zoom', $company->gallery_map_zoom ?? 13) }}" class="{{ $input }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('settings.map_zoom_hint') }}</p>
                    @error('gallery_map_zoom')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Videos --}}
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.video_heading') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('settings.video_hint') }}</p>
            <div class="mt-3">
                <label for="gallery_ffmpeg_path" class="block text-sm font-medium text-gray-700">{{ __('settings.ffmpeg_path') }}</label>
                <input type="text" id="gallery_ffmpeg_path" name="gallery_ffmpeg_path" value="{{ old('gallery_ffmpeg_path', $company->gallery_ffmpeg_path) }}" placeholder="ffmpeg" class="{{ $input }} font-mono">
                <p class="mt-1 text-xs text-gray-500">{{ __('settings.ffmpeg_path_hint') }}</p>
                <p class="mt-1 text-xs">
                    <span class="text-gray-500">{{ __('settings.ffmpeg_resolved') }}:</span>
                    <span class="font-mono text-gray-700">{{ $ffmpegResolved }}</span>
                    @if ($ffmpegAvailable)
                        <span class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-green-700">{{ __('settings.ffmpeg_ok') }}</span>
                    @else
                        <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-red-700">{{ __('settings.ffmpeg_missing') }}</span>
                    @endif
                </p>
                @error('gallery_ffmpeg_path')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="mt-4 sm:max-w-xs">
                <label for="gallery_video_frame" class="block text-sm font-medium text-gray-700">{{ __('settings.video_frame') }}</label>
                <input type="number" min="0" max="600" id="gallery_video_frame" name="gallery_video_frame" value="{{ old('gallery_video_frame', $company->gallery_video_frame ?? 1) }}" class="{{ $input }}">
                <p class="mt-1 text-xs text-gray-500">{{ __('settings.video_frame_hint') }}</p>
                @error('gallery_video_frame')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.save') }}</button>
        </div>
    </form>

    {{-- Maintenance jobs: re-read metadata and regenerate thumbnails run
         independently so either can be triggered on its own. --}}
    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.maintenance_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('settings.maintenance_hint', ['count' => $photoCount]) }}</p>
        <div class="mt-3 flex flex-wrap gap-3">
            <form method="POST" action="{{ route('settings.gallery.rescan') }}">
                @csrf
                <button type="submit" @disabled($photoCount === 0)
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{{ __('settings.rescan') }}</button>
            </form>
            <form method="POST" action="{{ route('settings.gallery.regenerate') }}">
                @csrf
                <button type="submit" @disabled($photoCount === 0)
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{{ __('settings.regenerate') }}</button>
            </form>
            <form method="POST" action="{{ route('settings.gallery.rename') }}">
                @csrf
                <button type="submit" @disabled($photoCount === 0)
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50">{{ __('settings.rename') }}</button>
            </form>
            <form method="POST" action="{{ route('settings.gallery.run-all') }}">
                @csrf
                <button type="submit" @disabled($photoCount === 0)
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('settings.run_all_jobs') }}</button>
            </form>
        </div>
    </div>

    {{-- Live queue status. Pending and failed job counts are read from the queue
         backend; worker count and a completion estimate are not tracked without
         Horizon, so they are not shown. --}}
    <div class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
         x-data="{
            status: @js($queue),
            async refresh() {
                try {
                    const r = await fetch('{{ route('settings.gallery.queue-status') }}', { headers: { Accept: 'application/json' } });
                    if (r.ok) this.status = await r.json();
                } catch (e) { /* keep the last known values */ }
            },
         }"
         x-init="const t = setInterval(() => refresh(), 5000); refresh(); $el.addEventListener('alpine:destroyed', () => clearInterval(t));">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.queue_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('settings.queue_hint') }}</p>

        <p class="mt-2 text-xs text-gray-500">
            {{ __('settings.queue_connection') }}:
            <span class="font-mono text-gray-700" x-text="status.connection"></span>
            <span class="text-gray-400">(<span x-text="status.driver"></span>)</span>
        </p>

        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="rounded-md border border-gray-200 p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('settings.queue_pending') }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">
                    <span x-show="status.pending !== null" x-text="status.pending"></span>
                    <span x-show="status.pending === null" class="text-sm font-normal text-gray-400">{{ __('settings.queue_pending_unsupported') }}</span>
                </div>
            </div>
            <div class="rounded-md border border-gray-200 p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('settings.queue_failed') }}</div>
                <div class="mt-1 text-2xl font-semibold" :class="status.failed > 0 ? 'text-red-600' : 'text-gray-900'" x-text="status.failed"></div>
            </div>
        </div>

        <p class="mt-4 text-xs text-gray-500">{{ __('settings.queue_workers_note') }}</p>
    </div>
</x-layouts.app>
