<x-layouts.share :title="$calendar->name">
    <div class="mx-auto max-w-2xl px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $calendar->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ __('shares.public_calendar_hint') }}</p>
            </div>
            <a href="{{ route('public-share.ics', $share->token) }}"
                class="shrink-0 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('shares.public_subscribe') }}</a>
        </div>

        <ul class="mt-6 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            @forelse ($events as $event)
                <li class="flex items-center gap-3 px-4 py-3">
                    <span class="w-40 shrink-0 text-sm text-gray-500">
                        {{ $event->starts_at ? $event->starts_at->format($event->all_day ? 'Y-m-d' : 'Y-m-d H:i') : '' }}
                    </span>
                    <span class="truncate text-sm font-medium text-gray-900">{{ $event->summary ?: '—' }}</span>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-gray-500">{{ __('shares.public_no_events') }}</li>
            @endforelse
        </ul>
    </div>
</x-layouts.share>
