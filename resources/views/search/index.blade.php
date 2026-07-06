<x-layouts.app :title="__('pages.search.title')">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('pages.search.heading') }}</h1>

    <form method="GET" action="{{ route('search') }}" role="search" class="mt-4">
        <label for="search-input" class="sr-only">{{ __('pages.search.heading') }}</label>
        <input type="search" id="search-input" name="q" value="{{ $term }}" autofocus
            placeholder="{{ __('pages.search.placeholder') }}"
            class="block w-full rounded-md border-gray-300 dark:border-gray-700 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm">
    </form>

    @if ($term === '')
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">{{ __('pages.search.prompt') }}</p>
    @elseif ($total === 0)
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">{{ __('pages.search.no_results', ['term' => $term]) }}</p>
    @else
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">
            {{ $total }} {{ Str::plural('result', $total) }} for "<span class="font-medium">{{ $term }}</span>".
        </p>

        <div class="mt-4 space-y-6">
            @foreach ($groups as $group => $results)
                <section>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $group }} ({{ count($results) }})
                    </h2>
                    <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm text-sm">
                        @foreach ($results as $result)
                            <li>
                                <a href="{{ $result->url }}" class="block min-w-0 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $result->title }}</span>
                                    @if ($result->subtitle)
                                        <span class="block break-words text-gray-500 dark:text-gray-400">{{ $result->subtitle }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.app>
