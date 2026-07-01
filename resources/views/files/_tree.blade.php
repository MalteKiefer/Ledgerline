{{-- Recursive folder tree node. Expects $nodes (Collection<Folder>), $current (?Folder). --}}
@foreach ($nodes as $node)
    <li>
        <a href="{{ route('files.index', ['folder' => $node->id]) }}"
            @class([
                'flex items-center justify-between gap-2 rounded px-2 py-1 text-sm',
                'bg-gray-100 font-medium text-gray-900' => ($current?->id === $node->id),
                'text-gray-600 hover:bg-gray-50' => ($current?->id !== $node->id),
            ])>
            <span class="flex min-w-0 items-center gap-1.5">
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
                <span class="truncate">{{ $node->name }}</span>
            </span>
            <span class="shrink-0 text-xs text-gray-400">{{ $node->files_count }}</span>
        </a>
        @if ($node->children->isNotEmpty())
            <ul class="ml-3 border-l border-gray-100 pl-2">
                @include('files._tree', ['nodes' => $node->children, 'current' => $current])
            </ul>
        @endif
    </li>
@endforeach
