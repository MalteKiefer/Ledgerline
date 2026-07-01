<x-layouts.app :title="__('gallery.trash_title')">
    <p class="text-sm text-gray-500"><a href="{{ route('gallery.index') }}" class="hover:underline">{{ __('gallery.back') }}</a></p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('gallery.trash_heading') }}</h1>

    @if ($photos->isEmpty())
        <p class="mt-6 rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">{{ __('gallery.trash_empty') }}</p>
    @else
        <div class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
            @foreach ($photos as $photo)
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="aspect-square bg-gray-100">
                        <img src="{{ route('gallery.image', ['photo' => $photo, 'size' => 'thumb']) }}" alt="{{ $photo->name }}" loading="lazy" class="h-full w-full object-cover">
                    </div>
                    <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                        <form method="POST" action="{{ route('gallery.restore', $photo->id) }}">
                            @csrf
                            <button type="submit" class="text-gray-700 hover:text-gray-900">{{ __('gallery.restore') }}</button>
                        </form>
                        <x-confirm-action :action="route('gallery.force-destroy', $photo->id)" method="DELETE"
                            :trigger="__('gallery.delete_forever')" trigger-class="text-red-600 hover:text-red-800"
                            :message="__('gallery.delete_forever_confirm')" />
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6">{{ $photos->links() }}</div>
</x-layouts.app>
