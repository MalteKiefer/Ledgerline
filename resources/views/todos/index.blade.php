<x-layouts.app :title="__('todos.heading')">
    @php
        $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
        $filterUrl = fn (array $p) => route('todos.index', array_merge(['view' => $view, 'tag' => $activeTag ?: null, 'q' => $q ?: null], $p));
    @endphp

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('todos.heading') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('todos.subheading') }}</p>
        </div>
        <a href="{{ route('todos.index', ['new' => 1]) }}" class="shrink-0 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">+ {{ __('todos.new_task') }}</a>
    </div>

    <div class="mt-6 flex flex-col gap-4 md:flex-row">
        {{-- Sidebar: filters, lists, tags --}}
        <aside class="w-full shrink-0 space-y-4 md:w-64">
            <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                <a href="{{ $filterUrl(['view' => 'all', 'tag' => null]) }}" class="block rounded px-3 py-1.5 {{ $view === 'all' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}">{{ __('todos.all') }}</a>
                <a href="{{ $filterUrl(['view' => 'marked', 'tag' => null]) }}" class="flex items-center gap-2 rounded px-3 py-1.5 {{ $view === 'marked' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}"><x-icon name="heart" class="h-4 w-4" />{{ __('todos.marked') }}</a>
                <a href="{{ $filterUrl(['view' => 'trash', 'tag' => null]) }}" class="flex items-center justify-between rounded px-3 py-1.5 {{ $view === 'trash' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}">
                    <span class="flex items-center gap-2"><x-icon name="trash" class="h-4 w-4" />{{ __('todos.trash') }}</span>
                    @if ($trashCount)<span class="text-xs text-gray-400">{{ $trashCount }}</span>@endif
                </a>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('todos.lists') }}</p>
                @foreach ($lists as $l)
                    <div class="flex items-center justify-between rounded px-3 py-1.5 {{ $view === 'list:'.$l->id ? 'bg-gray-100' : 'hover:bg-gray-50' }}">
                        <a href="{{ $filterUrl(['view' => 'list:'.$l->id, 'tag' => null]) }}" class="min-w-0 flex-1 truncate {{ $view === 'list:'.$l->id ? 'font-medium text-gray-900' : 'text-gray-700' }}">{{ $l->name }}</a>
                        <form method="POST" action="{{ route('todos.lists.destroy', $l) }}" onsubmit="return confirm(@js(__('todos.delete_list_confirm')))">
                            @csrf @method('DELETE')
                            <button type="submit" title="{{ __('todos.delete_list') }}" class="rounded p-0.5 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('todos.lists.store') }}" class="mt-1 flex items-center gap-1 px-1">
                    @csrf
                    <input type="text" name="name" required placeholder="{{ __('todos.new_list_placeholder') }}" class="w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
                    <button type="submit" title="{{ __('todos.add_list') }}" class="shrink-0 rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50"><x-icon name="plus" class="h-4 w-4" /></button>
                </form>
            </div>

            @if (count($allTags))
                <div class="rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-sm">
                    <p class="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('todos.tags') }}</p>
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
                <form method="GET" action="{{ route('todos.index') }}" class="flex-1">
                    <input type="hidden" name="view" value="{{ $view }}">
                    @if ($activeTag)<input type="hidden" name="tag" value="{{ $activeTag }}">@endif
                    <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('todos.search') }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                </form>
                @if ($view === 'trash' && $trashCount)
                    <form method="POST" action="{{ route('todos.trash.empty') }}" onsubmit="return confirm(@js(__('todos.empty_trash_confirm')))">
                        @csrf @method('DELETE')
                        <button type="submit" class="shrink-0 rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('todos.empty_trash') }}</button>
                    </form>
                @endif
            </div>

            @if ($editing)
                @include('todos._editor', ['editing' => $editing, 'lists' => $lists])
            @endif

            <ul class="mt-4 space-y-2">
                @foreach ($tasks as $t)
                    <li class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
                        <form method="POST" action="{{ route('todos.done', $t) }}" class="mt-0.5">
                            @csrf
                            <button type="submit" class="flex h-5 w-5 items-center justify-center rounded border text-xs {{ $t->done ? 'border-gray-800 bg-gray-800 text-white' : 'border-gray-300 text-transparent hover:border-gray-500' }}" aria-label="{{ __('todos.done') }}">✓</button>
                        </form>
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $t->priority === 'high' ? 'bg-red-500' : ($t->priority === 'low' ? 'bg-gray-300' : 'bg-amber-400') }}" title="{{ $t->priority }}"></span>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $filterUrl(['edit' => $t->id]) }}" class="block">
                                <p class="truncate text-sm font-medium {{ $t->done ? 'text-gray-400 line-through' : 'text-gray-900' }}">{{ $t->title }}</p>
                                @if ($t->description)<p class="truncate text-xs text-gray-500">{{ $t->description }}</p>@endif
                            </a>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                @if ($t->due_at)
                                    @php $overdue = ! $t->done && $t->due_at->isPast(); @endphp
                                    <span class="rounded px-1.5 py-0.5 text-[11px] {{ $overdue ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }}">{{ $t->due_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                                @endif
                                @foreach (($t->tags ?? []) as $g)
                                    <a href="{{ $filterUrl(['tag' => $g]) }}" class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 hover:bg-gray-200">{{ $g }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($t->url)<a href="{{ $t->url }}" target="_blank" rel="noopener" title="{{ __('todos.open_link') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-uturn-right" class="h-4 w-4" /></a>@endif
                            <form method="POST" action="{{ route('todos.mark', $t) }}">@csrf<button type="submit" title="{{ __('todos.marked_label') }}" class="rounded p-1 {{ $t->marked ? 'text-red-500' : 'text-gray-300 hover:text-gray-500' }}"><x-icon name="heart" class="h-4 w-4" /></button></form>
                            @if ($view === 'trash')
                                <form method="POST" action="{{ route('todos.restore', $t) }}">@csrf<button type="submit" title="{{ __('todos.restore') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button></form>
                                <form method="POST" action="{{ route('todos.destroy', $t) }}" onsubmit="return confirm(@js(__('todos.delete_confirm')))">@csrf @method('DELETE')<button type="submit" title="{{ __('todos.delete') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"><x-icon name="x-mark" class="h-4 w-4" /></button></form>
                            @else
                                <form method="POST" action="{{ route('todos.trash', $t) }}">@csrf<button type="submit" title="{{ __('todos.delete') }}" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button></form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if (! count($tasks))<p class="mt-10 text-center text-sm text-gray-500">{{ __('todos.empty') }}</p>@endif
        </section>
    </div>
</x-layouts.app>
