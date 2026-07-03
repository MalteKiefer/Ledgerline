@php
    $j = $job ?? null;
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
@endphp
<form method="POST" action="{{ $action }}"
    x-data="{
        encrypt: {{ old('encrypt', $j->encrypt ?? false) ? 'true' : 'false' }},
        source: '{{ old('source', $j->source ?? 'database') }}',
        mode: '{{ old('mode', $j->mode ?? 'mirror') }}',
        get canChooseMode() { return this.source === 'files' || this.source === 'gallery'; },
        get isArchive() { return this.source === 'database' || (this.canChooseMode && this.mode === 'archive'); },
    }" class="space-y-3">
    @csrf
    @if ($j) @method('PUT') @endif
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_name') }}</label>
            <input type="text" name="name" value="{{ old('name', $j->name ?? '') }}" required class="{{ $input }}">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_source') }}</label>
            <select name="source" x-model="source" class="{{ $input }}">
                @foreach (['database', 'files', 'gallery'] as $src)
                    <option value="{{ $src }}" @selected(old('source', $j->source ?? '') === $src)>{{ __('settings.backup_source_'.$src) }}</option>
                @endforeach
            </select>
            <p x-show="canChooseMode && mode === 'mirror'" x-cloak class="mt-1 text-xs text-gray-500">{{ __('settings.backup_mirror_hint') }}</p></div>
        <div x-show="canChooseMode" x-cloak><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_mode') }}</label>
            <select name="mode" x-model="mode" class="{{ $input }}">
                <option value="mirror">{{ __('settings.backup_mode_mirror') }}</option>
                <option value="archive">{{ __('settings.backup_mode_archive') }}</option>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_destination') }}</label>
            <select name="backup_destination_id" class="{{ $input }}">
                @foreach ($destinations as $dest)
                    <option value="{{ $dest->id }}" @selected(old('backup_destination_id', $j->backup_destination_id ?? '') == $dest->id)>{{ $dest->name }}</option>
                @endforeach
            </select></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_cron') }}</label>
            <input type="text" name="cron" value="{{ old('cron', $j->cron ?? '0 3 * * *') }}" class="{{ $input }}">
            <p class="mt-1 text-xs text-gray-500">{{ __('settings.backup_cron_hint') }} {{ __('settings.backup_cron_tz', ['tz' => config('app.timezone')]) }}</p>
            @error('cron')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div x-show="isArchive" x-cloak><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_retention') }}</label>
            <input type="number" name="retention" min="1" value="{{ old('retention', $j->retention ?? 7) }}" class="{{ $input }}"></div>
        @php $sel = (array) old('notify_channels', $j->notify_channels ?? []); @endphp
        <div class="sm:col-span-2">
            <span class="block text-sm font-medium text-gray-700">{{ __('settings.backup_notify') }}</span>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                @foreach (['desktop' => __('settings.backup_notify_desktop'), 'mail' => __('settings.notify_mail_heading'), 'ntfy' => 'NTFY', 'webhook' => 'Webhook'] as $ch => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="notify_channels[]" value="{{ $ch }}" @checked(in_array($ch, $sel, true))
                            @if ($ch === 'desktop') @change="if ($event.target.checked && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission()" @endif
                            class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-start gap-6">
        <label x-show="isArchive" x-cloak class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="encrypt" value="1" x-model="encrypt" class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            {{ __('settings.backup_encrypt') }}</label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $j->enabled ?? true)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            {{ __('settings.backup_enabled') }}</label>
        <div x-show="encrypt && isArchive" x-cloak class="min-w-[16rem]">
            <label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_passphrase') }}</label>
            <input type="password" name="passphrase" value="" class="{{ $input }}" autocomplete="new-password" placeholder="{{ $j ? '••••••••' : '' }}">
            @if ($j)<p class="mt-1 text-xs text-gray-500">{{ __('settings.notify_secret_keep_hint') }}</p>@endif
            @error('passphrase')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.backup_save') }}</button>
</form>
