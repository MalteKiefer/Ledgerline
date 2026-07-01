{{-- Recursive radio tree for the move modal. Expects $nodes, $depth (int). --}}
@foreach ($nodes as $node)
    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50" style="padding-left: {{ 0.5 + $depth * 1.1 }}rem">
        <input type="radio" :name="radioName" value="{{ $node->id }}" x-model.number="target"
            class="border-gray-300 text-gray-800 focus:ring-gray-500">
        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" /></svg>
        {{ $node->name }}
    </label>
    @if ($node->children->isNotEmpty())
        @include('files._tree_select', ['nodes' => $node->children, 'depth' => $depth + 1])
    @endif
@endforeach
