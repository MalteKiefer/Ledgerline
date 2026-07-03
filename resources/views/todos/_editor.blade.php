@php
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
    $isNew = ! $editing->exists;
    $due = $editing->due_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
    $channels = $editing->reminder_channels ?? [];
@endphp

<form method="POST" action="{{ $isNew ? route('todos.store') : route('todos.update', $editing) }}"
    class="mt-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
    @csrf
    @unless ($isNew) @method('PUT') @endunless
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">{{ $isNew ? __('todos.new_task') : __('todos.edit') }}</h2>
        <a href="{{ route('todos.index') }}" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('todos.cancel') }}"><x-icon name="x-mark" class="h-5 w-5" /></a>
    </div>

    @if ($errors->any())
        <p class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
    @endif

    <div class="mt-3 space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('todos.title') }}</label>
            <input type="text" name="title" required value="{{ old('title', $editing->title) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('todos.description') }}</label>
            <textarea name="description" rows="3" class="{{ $input }}">{{ old('description', $editing->description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('todos.url') }}</label>
            <input type="url" name="url" placeholder="https://…" value="{{ old('url', $editing->url) }}" class="{{ $input }}">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('todos.priority') }}</label>
                <select name="priority" class="{{ $input }}">
                    @foreach (['low' => __('todos.priority_low'), 'normal' => __('todos.priority_normal'), 'high' => __('todos.priority_high')] as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('priority', $editing->priority ?? 'normal') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('todos.list') }}</label>
                <select name="todo_list_id" class="{{ $input }}">
                    <option value="">{{ __('todos.no_list') }}</option>
                    @foreach ($lists as $l)
                        <option value="{{ $l->id }}" @selected((int) old('todo_list_id', $editing->todo_list_id) === $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('todos.due') }}</label>
            <input type="datetime-local" name="due" value="{{ old('due', $due) }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('todos.notify_heading') }}</label>
            <div class="mt-1 flex flex-wrap gap-3 text-sm text-gray-700">
                @foreach (['desktop' => __('todos.channel_browser'), 'ntfy' => __('todos.channel_ntfy'), 'mail' => __('todos.channel_mail'), 'webhook' => __('todos.channel_webhook')] as $val => $lbl)
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="reminder_channels[]" value="{{ $val }}" @checked(in_array($val, old('reminder_channels', $channels), true)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">{{ $lbl }}</label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ __('todos.notify_hint') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ __('todos.tags') }}</label>
            <input type="text" name="tags" value="{{ old('tags', implode(', ', $editing->tags ?? [])) }}" placeholder="{{ __('todos.tags_placeholder') }}" class="{{ $input }}">
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="marked" value="1" @checked(old('marked', $editing->marked)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            <span class="text-sm text-gray-700">{{ __('todos.marked_label') }}</span>
        </label>
    </div>

    <div class="mt-5 flex items-center justify-end gap-3">
        <a href="{{ route('todos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('todos.cancel') }}</a>
        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('todos.save') }}</button>
    </div>
</form>
