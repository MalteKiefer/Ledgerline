<x-layouts.app :title="__('notes.title')">
    @php
        $filterUrl = fn (array $p) => route('notes.index', array_merge(['view' => $view, 'tag' => $activeTag ?: null, 'q' => $q ?: null], $p));
    @endphp

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('notes.heading') }}</h1>
        <a href="{{ route('notes.index', ['new' => 1]) }}" class="shrink-0 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ {{ __('notes.new_note') }}</a>
    </div>

    @if (session('share_url'))
        <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm">
            <p class="font-medium text-green-800">{{ __('notes.share_created') }}</p>
            <input type="text" readonly value="{{ session('share_url') }}" onclick="this.select()" class="mt-1 w-full rounded-md border-gray-300 bg-white text-xs shadow-sm">
        </div>
    @endif

    <div class="mt-6 flex flex-col gap-4 md:flex-row" style="min-height: calc(100vh - 16rem);">
        {{-- List pane --}}
        <aside class="flex w-full flex-col md:w-80 md:shrink-0">
            <form method="GET" action="{{ route('notes.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="view" value="{{ $view }}">
                @if ($activeTag)<input type="hidden" name="tag" value="{{ $activeTag }}">@endif
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('notes.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
            </form>

            <div class="mt-2 flex items-center gap-3 text-xs">
                <a href="{{ $filterUrl(['view' => 'active', 'tag' => null]) }}" class="{{ $view !== 'trash' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">{{ __('notes.active') }}</a>
                <a href="{{ $filterUrl(['view' => 'trash', 'tag' => null]) }}" class="{{ $view === 'trash' ? 'font-semibold text-gray-900' : 'text-gray-500 hover:text-gray-700' }}">{{ __('notes.trash') }} ({{ $trashCount }})</a>
                @if ($view === 'trash' && $trashCount)
                    <form method="POST" action="{{ route('notes.trash.empty') }}" onsubmit="return confirm(@js(__('notes.empty_trash_confirm')))" class="ml-auto">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-700">{{ __('notes.empty_trash') }}</button>
                    </form>
                @endif
            </div>

            @if (count($allTags))
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($allTags as $t)
                        <a href="{{ $filterUrl(['tag' => $activeTag === $t ? null : $t]) }}" class="rounded px-2 py-0.5 text-xs {{ $activeTag === $t ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $t }}</a>
                    @endforeach
                </div>
            @endif

            <div class="mt-3 min-h-0 flex-1 space-y-2 overflow-y-auto">
                @foreach ($notes as $n)
                    <a href="{{ $filterUrl(['open' => $n->id]) }}" class="block rounded-lg border bg-white p-3 shadow-sm hover:bg-gray-50 {{ $current && $current->id === $n->id ? 'border-gray-800' : 'border-gray-200' }}">
                        <div class="flex items-center gap-2">
                            @if ($n->pinned)<x-icon name="bookmark-solid" class="h-3.5 w-3.5 shrink-0 text-gray-500" />@endif
                            <p class="truncate text-sm font-medium text-gray-900">{{ $n->title ?: __('notes.untitled') }}</p>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-gray-500">{{ \Illuminate\Support\Str::of($n->content ?? '')->stripTags()->replaceMatches('/[#*_`>\[\]()-]/', '')->limit(80) }}</p>
                    </a>
                @endforeach
                @if (! count($notes))<p class="px-2 py-8 text-center text-sm text-gray-500">{{ __('notes.empty') }}</p>@endif
            </div>
        </aside>

        {{-- Editor / preview pane --}}
        <section class="min-w-0 flex-1">
            @if ($current)
                @include('notes._editor', ['current' => $current, 'currentHtml' => $currentHtml, 'lifetimes' => $lifetimes])
            @else
                <div class="flex h-full items-center justify-center rounded-lg border border-dashed border-gray-300 text-sm text-gray-400">{{ __('notes.pick_note') }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
