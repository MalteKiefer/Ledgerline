{{-- Timeline sections for one page. Reused by the index and the infinite-scroll
     feed. Each tile carries data-* so the viewer can read metadata without a
     round-trip. --}}
@php
    $fmtBytes = static function (?int $bytes): string {
        if (! $bytes) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
        return number_format($v, $i ? 1 : 0).' '.$units[$i];
    };
@endphp

@foreach ($grouped as $day => $dayPhotos)
    <section data-day="{{ $day }}">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('LL') }}</h2>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6" data-day-grid>
            @foreach ($dayPhotos as $photo)
                <div class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100">
                    @if ($photo->isReady())
                        <button type="button" data-photo
                            data-id="{{ $photo->id }}"
                            data-medium="{{ route('gallery.image', ['photo' => $photo, 'size' => 'medium']) }}"
                            data-original="{{ route('gallery.image', ['photo' => $photo, 'size' => 'original']) }}"
                            data-name="{{ $photo->name }}"
                            data-date="{{ $photo->taken_at->isoFormat('LL') }}"
                            data-dateiso="{{ $photo->taken_at->format('Y-m-d') }}"
                            data-time="{{ $photo->taken_at->format('H:i') }}"
                            data-camera="{{ $photo->camera }}"
                            data-dims="{{ $photo->width && $photo->height ? $photo->width.' × '.$photo->height : '' }}"
                            data-size="{{ $fmtBytes($photo->size) }}"
                            data-lat="{{ $photo->latitude }}"
                            data-lng="{{ $photo->longitude }}"
                            @click="openViewer($el)"
                            class="block h-full w-full">
                            <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy"
                                class="h-full w-full object-cover transition group-hover:opacity-90">
                        </button>
                        <input type="checkbox" value="{{ $photo->id }}" x-model.number="selected"
                            class="absolute left-1.5 top-1.5 rounded border-gray-300 text-gray-800 opacity-0 focus:ring-gray-500 group-hover:opacity-100"
                            :class="selected.includes({{ $photo->id }}) ? '!opacity-100' : ''">
                    @else
                        <div class="flex h-full w-full animate-pulse items-center justify-center bg-gray-200 text-xs text-gray-400">{{ __('gallery.processing') }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endforeach

@if (! empty($hasMore))<div data-has-more="1" hidden></div>@endif
