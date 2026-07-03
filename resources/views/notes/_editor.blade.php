@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $isNew = ! $current->exists;
@endphp

<div class="flex h-full flex-col gap-4 lg:flex-row">
    {{-- Editor form --}}
    <form method="POST" action="{{ $isNew ? route('notes.store') : route('notes.update', $current) }}" class="flex min-w-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        @if ($errors->any())
            <p class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif

        <input type="text" name="title" value="{{ old('title', $current->title) }}" placeholder="{{ __('notes.title_placeholder') }}" class="w-full border-0 border-b border-gray-100 px-0 text-lg font-semibold text-gray-900 focus:border-gray-400 focus:ring-0">
        <input type="text" name="tags" value="{{ old('tags', implode(', ', $current->tags ?? [])) }}" placeholder="{{ __('notes.tags_placeholder') }}" class="mt-2 w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <textarea name="content" rows="16" placeholder="{{ __('notes.content') }}" class="mt-3 min-h-0 flex-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">{{ old('content', $current->content) }}</textarea>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('notes.save') }}</button>
            <label class="flex items-center gap-1.5 text-sm text-gray-700">
                <input type="checkbox" name="pinned" value="1" @checked(old('pinned', $current->pinned)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">{{ __('notes.pin') }}
            </label>
            @unless ($isNew)
                <span class="ml-auto flex items-center gap-1">
                    <a href="{{ route('notes.index') }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">{{ __('notes.cancel') }}</a>
                </span>
            @endunless
        </div>
    </form>

    @unless ($isNew)
        {{-- Preview + actions + share --}}
        <div class="min-w-0 flex-1 space-y-4 overflow-y-auto lg:max-w-md">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('notes.preview') }}</h3>
                    <div class="flex items-center gap-1">
                        <form method="POST" action="{{ route('notes.pin', $current) }}">@csrf<button type="submit" title="{{ $current->pinned ? __('notes.unpin') : __('notes.pin') }}" class="rounded p-1 {{ $current->pinned ? 'text-gray-800' : 'text-gray-400 hover:text-gray-600' }}"><x-icon name="bookmark" class="h-4 w-4" /></button></form>
                        @if ($view === 'trash')
                            <form method="POST" action="{{ route('notes.restore', $current) }}">@csrf<button type="submit" title="{{ __('notes.restore') }}" class="rounded p-1 text-gray-400 hover:text-gray-700"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button></form>
                            <form method="POST" action="{{ route('notes.destroy', $current) }}" onsubmit="return confirm(@js(__('notes.delete_confirm')))">@csrf @method('DELETE')<button type="submit" title="{{ __('notes.delete_forever') }}" class="rounded p-1 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button></form>
                        @else
                            <form method="POST" action="{{ route('notes.trash', $current) }}">@csrf<button type="submit" title="{{ __('notes.to_trash') }}" class="rounded p-1 text-gray-400 hover:text-red-600"><x-icon name="trash" class="h-4 w-4" /></button></form>
                        @endif
                    </div>
                </div>
                <div class="prose prose-sm max-w-none text-gray-800">{!! $currentHtml !!}</div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('notes.share_title') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('notes.share_intro') }}</p>
                <form method="POST" action="{{ route('notes.share', $current) }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_expiry') }}</label>
                        <select name="expires_in" class="{{ $input }}">
                            <option value="3600">{{ __('notes.share_expiry_1h') }}</option>
                            <option value="86400" selected>{{ __('notes.share_expiry_24h') }}</option>
                            <option value="604800">{{ __('notes.share_expiry_7d') }}</option>
                            <option value="2592000">{{ __('notes.share_expiry_30d') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_password') }}</label>
                        <input type="text" name="password" autocomplete="off" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">{{ __('notes.share_max_views') }}</label>
                        <input type="number" name="max_views" min="1" class="{{ $input }}">
                    </div>
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('notes.share_create') }}</button>
                </form>
            </div>
        </div>
    @endunless
</div>
