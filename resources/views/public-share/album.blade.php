<x-layouts.share :title="$album->name">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <h1 class="text-2xl font-semibold text-gray-900">{{ $album->name }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('shares.public_album_hint') }}</p>

        @if ($photos->isEmpty())
            <p class="mt-8 text-center text-sm text-gray-500">{{ __('shares.public_no_photos') }}</p>
        @else
            <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-2 sm:grid-cols-4 md:grid-cols-6">
                @foreach ($photos as $photo)
                    <a href="{{ route('public-share.photo', ['publicShare' => $share->token, 'photo' => $photo->id, 'size' => 'original']) }}"
                        class="block aspect-square overflow-hidden rounded-lg bg-gray-100">
                        <img src="{{ route('public-share.photo', ['publicShare' => $share->token, 'photo' => $photo->id, 'size' => 'thumb']) }}"
                            alt="" class="h-full w-full object-cover" loading="lazy">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.share>
