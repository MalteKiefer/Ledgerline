<x-layouts.app :title="__('settings.gallery_heading')">
    @php $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm'; @endphp

    <p class="text-sm text-gray-500">
        <a href="{{ route('settings') }}" class="hover:underline">{{ __('messages.menu.settings') }}</a>
        <span aria-hidden="true">/</span> {{ __('settings.gallery_section') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('settings.gallery_heading') }}</h1>

    {{-- Trip thresholds --}}
    <form method="POST" action="{{ route('settings.gallery.update') }}" class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <h2 class="text-sm font-semibold text-gray-900">{{ __('settings.trips_heading') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('settings.trips_hint') }}</p>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        </div>
        <div class="mt-4">
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
        </div>
    </div>
</x-layouts.app>
