@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $isNew = ! $editing->exists;
@endphp

<form method="POST" action="{{ $isNew ? route('bookmarks.store') : route('bookmarks.update', $editing) }}"
    class="mt-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">{{ $isNew ? __('bookmarks.add_title') : __('bookmarks.edit_title') }}</h2>
        <a href="{{ route('bookmarks.index') }}" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('bookmarks.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></a>
    </div>

    @if ($errors->any())
        <p class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
    @endif

    <div class="mt-3 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('bookmarks.url') }}</label>
            <input type="url" name="url" required value="{{ old('url', $editing->url) }}" placeholder="https://…" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('bookmarks.field_title') }}</label>
            <input type="text" name="title" required value="{{ old('title', $editing->title) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('bookmarks.description') }}</label>
            <textarea name="description" rows="3" class="{{ $input }}">{{ old('description', $editing->description) }}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('bookmarks.field_folder') }}</label>
                <select name="bookmark_folder_id" class="{{ $input }}">
                    <option value="">{{ __('bookmarks.no_folder') }}</option>
                    @foreach ($folders as $f)
                        <option value="{{ $f->id }}" @selected((int) old('bookmark_folder_id', $editing->bookmark_folder_id) === $f->id)>{{ $f->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('bookmarks.tags') }}</label>
                <input type="text" name="tags" value="{{ old('tags', implode(', ', $editing->tags ?? [])) }}" placeholder="{{ __('bookmarks.tags_placeholder') }}" class="{{ $input }}">
            </div>
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="favorite" value="1" @checked(old('favorite', $editing->favorite)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            <span class="text-sm text-gray-700">{{ __('bookmarks.field_favorite') }}</span>
        </label>
    </div>

    <div class="mt-5 flex items-center justify-end gap-3">
        <a href="{{ route('bookmarks.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('bookmarks.cancel') }}</a>
        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('bookmarks.save') }}</button>
    </div>
</form>
