<x-layouts.app :title="__('bookmarks.title')">
    @php
        $filterUrl = fn (array $p) => route('bookmarks.index', array_merge(['view' => $view, 'tag' => $activeTag ?: null, 'q' => $q ?: null], $p));
    @endphp

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('bookmarks.title') }}</h1>
        <a href="{{ route('bookmarks.index', ['new' => 1]) }}" class="shrink-0 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ {{ __('bookmarks.new_bookmark') }}</a>
    </div>

    <div class="mt-6 flex flex-col gap-4 md:flex-row">
        {{-- Sidebar --}}
        <aside class="w-full shrink-0 space-y-4 md:w-64">
            <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                <a href="{{ $filterUrl(['view' => 'all', 'tag' => null]) }}" class="block rounded px-3 py-1.5 {{ $view === 'all' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}">{{ __('bookmarks.all') }}</a>
                <a href="{{ $filterUrl(['view' => 'favorites', 'tag' => null]) }}" class="flex items-center gap-2 rounded px-3 py-1.5 {{ $view === 'favorites' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}"><x-icon name="heart" class="h-4 w-4" />{{ __('bookmarks.favorites') }}</a>
                <a href="{{ $filterUrl(['view' => 'trash', 'tag' => null]) }}" class="flex items-center justify-between rounded px-3 py-1.5 {{ $view === 'trash' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}">
                    <span class="flex items-center gap-2"><x-icon name="trash" class="h-4 w-4" />{{ __('bookmarks.trash') }}</span>
                    @if ($trashCount)<span class="text-xs text-gray-400">{{ $trashCount }}</span>@endif
                </a>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('bookmarks.folders') }}</p>
                @foreach ($folders as $f)
                    <div class="flex items-center justify-between rounded px-3 py-1.5 {{ $view === 'folder:'.$f->id ? 'bg-gray-100' : 'hover:bg-gray-50' }}">
                        <a href="{{ $filterUrl(['view' => 'folder:'.$f->id, 'tag' => null]) }}" class="min-w-0 flex-1 truncate {{ $view === 'folder:'.$f->id ? 'font-medium text-gray-900' : 'text-gray-700' }}">{{ $f->name }}</a>
                        <form method="POST" action="{{ route('bookmarks.folders.destroy', $f) }}" onsubmit="return confirm(@js(__('bookmarks.delete_folder_confirm')))">
                            @csrf @method('DELETE')
                            <button type="submit" title="{{ __('bookmarks.delete_folder') }}" class="rounded p-0.5 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('bookmarks.folders.store') }}" class="mt-1 flex items-center gap-1 px-1">
                    @csrf
                    <input type="text" name="name" required placeholder="{{ __('bookmarks.new_folder') }}" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="submit" title="{{ __('bookmarks.new_folder') }}" class="shrink-0 rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="plus" class="h-4 w-4" /></button>
                </form>
            </div>

            @if (count($allTags))
                <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('bookmarks.tags') }}</p>
                    <div class="flex flex-wrap gap-1 px-2 py-1">
                        @foreach ($allTags as $t)
                            <a href="{{ $filterUrl(['tag' => $activeTag === $t ? null : $t]) }}" class="rounded px-2 py-0.5 text-xs {{ $activeTag === $t ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $t }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>

        {{-- Main --}}
        <section class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('bookmarks.index') }}" class="flex-1">
                    <input type="hidden" name="view" value="{{ $view }}">
                    @if ($activeTag)<input type="hidden" name="tag" value="{{ $activeTag }}">@endif
                    <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('bookmarks.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </form>
                @if ($view === 'trash' && $trashCount)
                    <form method="POST" action="{{ route('bookmarks.trash.empty') }}" onsubmit="return confirm(@js(__('bookmarks.empty_trash_confirm')))">
                        @csrf @method('DELETE')
                        <button type="submit" class="shrink-0 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('bookmarks.empty_trash') }}</button>
                    </form>
                @endif
            </div>

            @if ($editing)
                @include('bookmarks._editor', ['editing' => $editing, 'folders' => $folders])
            @endif

            <ul class="mt-4 space-y-2">
                @foreach ($bookmarks as $b)
                    @php $host = parse_url($b->url, PHP_URL_HOST); @endphp
                    <li class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                        @if ($host)
                            <img src="https://{{ $host }}/favicon.ico" alt="" referrerpolicy="no-referrer" onerror="this.style.display='none'" class="mt-0.5 h-5 w-5 shrink-0 rounded">
                        @endif
                        <div class="min-w-0 flex-1">
                            <a href="{{ $b->url }}" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-gray-900 hover:underline">{{ $b->title }}</a>
                            <p class="truncate text-xs text-gray-400">{{ $b->url }}</p>
                            @if ($b->description)<p class="truncate text-xs text-gray-500">{{ \Illuminate\Support\Str::of($b->description)->stripTags()->limit(100) }}</p>@endif
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                @foreach (($b->tags ?? []) as $g)
                                    <a href="{{ $filterUrl(['tag' => $g]) }}" class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 hover:bg-gray-200">{{ $g }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <form method="POST" action="{{ route('bookmarks.favorite', $b) }}">@csrf<button type="submit" title="{{ $b->favorite ? __('bookmarks.unfavorite') : __('bookmarks.favorite') }}" class="rounded p-1 {{ $b->favorite ? 'text-red-500' : 'text-gray-300 hover:text-gray-500' }}"><x-icon name="heart" class="h-4 w-4" /></button></form>
                            @if ($view !== 'trash')
                                <a href="{{ $filterUrl(['edit' => $b->id]) }}" title="{{ __('bookmarks.edit') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icon name="pencil" class="h-4 w-4" /></a>
                                <form method="POST" action="{{ route('bookmarks.trash', $b) }}">@csrf<button type="submit" title="{{ __('bookmarks.to_trash') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button></form>
                            @else
                                <form method="POST" action="{{ route('bookmarks.restore', $b) }}">@csrf<button type="submit" title="{{ __('bookmarks.restore') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button></form>
                                <form method="POST" action="{{ route('bookmarks.destroy', $b) }}" onsubmit="return confirm(@js(__('bookmarks.delete_confirm')))">@csrf @method('DELETE')<button type="submit" title="{{ __('bookmarks.delete_forever') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button></form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if (! count($bookmarks))<p class="mt-10 text-center text-sm text-gray-500">{{ __('bookmarks.empty') }}</p>@endif
        </section>
    </div>
</x-layouts.app>
