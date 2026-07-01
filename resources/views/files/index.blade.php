<x-layouts.app title="Files">
    <h1 class="text-2xl font-semibold text-gray-900">Files</h1>
    <p class="mt-1 text-sm text-gray-600">All files across your team's customers and projects.</p>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('files.index') }}"
            @class([
                'rounded-full px-3 py-1',
                'bg-gray-800 text-white' => $activeType === null,
                'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== null,
            ])>All</a>
        @foreach ($types as $type)
            <a href="{{ route('files.index', ['type' => $type['value']]) }}"
                @class([
                    'rounded-full px-3 py-1',
                    'bg-gray-800 text-white' => $activeType === $type['value'],
                    'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== $type['value'],
                ])>{{ $type['label'] }}</a>
        @endforeach
    </div>

    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        @if ($files->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500">No files found.</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">File</th>
                        <th scope="col" class="px-4 py-3">Attached to</th>
                        <th scope="col" class="px-4 py-3">Type</th>
                        <th scope="col" class="px-4 py-3">Tags</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($files as $file)
                        @php $attached = $file->attachable; @endphp
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <a href="{{ route('files.download', $file) }}" class="hover:underline">{{ $file->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @if ($attached instanceof \App\Models\Customer)
                                    <a href="{{ route('customers.show', $attached) }}" class="hover:underline">{{ $attached->name }}</a>
                                @elseif ($attached instanceof \App\Models\Project)
                                    <a href="{{ route('projects.show', $attached) }}" class="hover:underline">{{ $attached->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $file->type->label() }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @forelse ($file->tags as $tag)
                                    <span class="mr-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $tag->name }}</span>
                                @empty
                                    <span class="text-gray-400">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">
        {{ $files->links() }}
    </div>
</x-layouts.app>
