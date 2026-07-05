{{-- Timeline sections for one page. Reused by the index and the infinite-scroll
     feed. Each tile carries data-* so the viewer can read metadata without a
     round-trip. --}}
@php
    $fmtDuration = static function (int $seconds): string {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    };
@endphp

@foreach ($grouped as $day => $dayPhotos)
    <section data-day="{{ $day }}" data-month="{{ \Illuminate\Support\Carbon::parse($day)->format('Y-m') }}">
        <label class="mb-3 flex w-max cursor-pointer items-center gap-2">
            <input type="checkbox" @click="toggleDay('{{ $day }}')" :checked="dayFullySelected('{{ $day }}')"
                class="rounded border-gray-300 text-gray-800 focus:ring-gray-500" aria-label="{{ __('gallery.select_day') }}">
            <span class="text-sm font-semibold text-gray-700">{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('LL') }}</span>
        </label>
        <div class="grid gap-2 grid-cols-3 sm:[grid-template-columns:repeat(var(--gallery-cols,6),minmax(0,1fr))]" data-day-grid>
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
                            data-size="{{ \App\Support\Bytes::format($photo->size) }}"
                            data-lat="{{ $photo->latitude }}"
                            data-lng="{{ $photo->longitude }}"
                            data-place="{{ $photo->place }}"
                            data-place-lines="{{ implode('|', $photo->placeLines()) }}"
                            data-favorite="{{ $photo->isFavorite() ? '1' : '0' }}"
                            data-media-type="{{ $photo->media_type }}"
                            data-mime="{{ $photo->mime_type }}"
                            data-video="{{ $photo->isVideo() ? route('gallery.video', $photo) : '' }}"
                            data-motion="{{ $photo->hasMotion() ? route('gallery.motion', $photo) : '' }}"
                            data-duration-text="{{ $photo->durationForHumans() }}"
                            data-fps="{{ $photo->fps() }}"
                            data-codec="{{ $photo->codec() }}"
                            data-focal="{{ $photo->focalLength() }}"
                            data-aperture="{{ $photo->aperture() }}"
                            data-shutter="{{ $photo->shutter() }}"
                            data-iso="{{ $photo->iso() }}"
                            @click="openViewer($el)"
                            @mouseenter="hoverMotion($el, true)" @mouseleave="hoverMotion($el, false)"
                            class="block h-full w-full">
                            <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy"
                                x-on:load="$el.classList.remove('opacity-0')" x-init="$el.complete && $el.classList.remove('opacity-0')"
                                class="h-full w-full object-cover opacity-0 transition-opacity duration-500 group-hover:opacity-90">
                            @if ($photo->isVideo())
                                <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white"><x-icon name="play" class="h-5 w-5" /></span>
                                </span>
                                @if ($photo->duration)
                                    <span class="pointer-events-none absolute bottom-1.5 right-1.5 rounded bg-black/60 px-1.5 py-0.5 text-xs font-medium text-white">{{ $fmtDuration($photo->duration) }}</span>
                                @endif
                            @elseif ($photo->hasMotion())
                                <span class="pointer-events-none absolute bottom-1.5 left-1.5 rounded bg-black/60 px-1.5 py-0.5 text-xs font-medium text-white">{{ __('gallery.motion') }}</span>
                            @endif
                        </button>
                        <input type="checkbox" value="{{ $photo->id }}" data-select
                            @click="toggleSelect($event, {{ $photo->id }})" :checked="selected.includes({{ $photo->id }})"
                            class="absolute left-1.5 top-1.5 rounded border-gray-300 text-gray-800 opacity-0 focus:ring-gray-500 group-hover:opacity-100"
                            :class="selected.includes({{ $photo->id }}) ? '!opacity-100' : ''">
                        <form method="POST" action="{{ route('gallery.favorite', $photo) }}" class="absolute right-1.5 top-1.5">
                            @csrf
                            <button type="submit" title="{{ __('gallery.favorite') }}"
                                @class([
                                    'text-lg drop-shadow',
                                    'text-red-500' => $photo->isFavorite(),
                                    'text-white/80 opacity-0 hover:text-white group-hover:opacity-100' => ! $photo->isFavorite(),
                                ])>@if ($photo->isFavorite())<x-icon name="heart-solid" />@else<x-icon name="heart" />@endif</button>
                        </form>
                    @elseif ($photo->status === 'failed')
                        <div class="flex h-full w-full items-center justify-center bg-red-50 text-center text-xs text-red-500">{{ __('gallery.failed') }}</div>
                        <input type="checkbox" value="{{ $photo->id }}" data-select
                            @click="toggleSelect($event, {{ $photo->id }})" :checked="selected.includes({{ $photo->id }})"
                            class="absolute left-1.5 top-1.5 rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                    @else
                        <div class="flex h-full w-full animate-pulse items-center justify-center bg-gray-200 text-xs text-gray-400">{{ __('gallery.processing') }}</div>
                        <input type="checkbox" value="{{ $photo->id }}" data-select
                            @click="toggleSelect($event, {{ $photo->id }})" :checked="selected.includes({{ $photo->id }})"
                            class="absolute left-1.5 top-1.5 rounded border-gray-300 text-gray-800 opacity-0 focus:ring-gray-500 group-hover:opacity-100"
                            :class="selected.includes({{ $photo->id }}) ? '!opacity-100' : ''">
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endforeach

@if (! empty($hasMore))<div data-has-more="1" hidden></div>@endif
