<x-layouts.app :title="$file->displayTitle">
    <p class="text-sm text-gray-500">
        <a href="{{ route('files.show', $file) }}" class="hover:underline">{{ __('files.view') }}</a>
        <span aria-hidden="true">/</span> {{ __('files.edit_file') }}
    </p>
    <h1 class="mt-1 text-2xl font-semibold text-gray-900 break-all">{{ $file->displayTitle }}</h1>

    @if ($editable)
        <form method="POST" action="{{ route('files.content', $file) }}" class="mt-6">
            @csrf
            @method('PUT')
            <textarea name="content" rows="24" spellcheck="false"
                class="block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">{{ $content }}</textarea>
            @error('content')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="mt-4 flex gap-3">
                <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.save') }}</button>
                <a href="{{ route('files.show', $file) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @else
        <p class="mt-6 rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 shadow-sm">
            {{ __('files.not_text_editable') }}
        </p>
        <div class="mt-4">
            <a href="{{ route('files.download', $file) }}" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('files.download') ?? 'Download' }}</a>
        </div>
    @endif
</x-layouts.app>
