@php
    $j = $job ?? null;
    $input = 'mt-1 block w-full rounded-md border-md-outline-variant dark:border-md-outline-variant shadow-sm focus:border-accent focus:ring-accent sm:text-sm';
@endphp
<form method="POST" action="{{ $action }}"
    x-data="{
        encrypt: {{ old('encrypt', $j->encrypt ?? false) ? 'true' : 'false' }},
    }" class="space-y-3">
    @csrf
    @if ($j) @method('PUT') @endif
    @php
        $selSources = (array) old('sources', $j ? $j->effectiveSources() : ['database']);
        $tiers = $j ? $j->retentionTiers() : ['daily' => 7, 'weekly' => 0, 'monthly' => 0];
    @endphp
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_name') }}</label>
            <input type="text" name="name" value="{{ old('name', $j->name ?? '') }}" required class="{{ $input }}">
            @error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
        <div><label class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_destination') }}</label>
            <select name="backup_destination_id" class="{{ $input }}">
                @foreach ($destinations as $dest)
                    <option value="{{ $dest->id }}" @selected(old('backup_destination_id', $j->backup_destination_id ?? '') == $dest->id)>{{ $dest->name }}</option>
                @endforeach
            </select></div>
        <div class="sm:col-span-2">
            <span class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_sources') }}</span>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                @foreach (\App\Models\BackupJob::SOURCES as $src)
                    <label class="flex items-center gap-2 text-sm text-md-on-surface-var dark:text-md-on-surface-var">
                        <input type="checkbox" name="sources[]" value="{{ $src }}" @checked(in_array($src, $selSources, true)) class="rounded border-md-outline-variant dark:border-md-outline-variant focus:ring-accent">
                        {{ __('settings.backup_source_'.$src) }}
                    </label>
                @endforeach
            </div>
            @error('sources')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div><label class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_mode') }}</label>
            <select name="mode" class="{{ $input }}">
                <option value="full" @selected(old('mode', $j->mode ?? 'full') === 'full')>{{ __('settings.backup_mode_full') }}</option>
                <option value="incremental" @selected(old('mode', $j->mode ?? 'full') === 'incremental')>{{ __('settings.backup_mode_incremental') }}</option>
            </select>
            <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_mode_hint') }}</p></div>
        <div><label class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_cron') }}</label>
            <input type="text" name="cron" value="{{ old('cron', $j->cron ?? '0 3 * * *') }}" class="{{ $input }}">
            <p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_cron_hint') }} {{ __('settings.backup_cron_tz', ['tz' => config('app.timezone')]) }}</p>
            @error('cron')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2">
            <span class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_retention_gfs') }}</span>
            <p class="text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_retention_gfs_hint') }}</p>
            <div class="mt-1 grid gap-3 sm:grid-cols-3">
                <div><label class="block text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_keep_daily') }}</label>
                    <input type="number" name="keep_daily" min="0" value="{{ old('keep_daily', $tiers['daily']) }}" class="{{ $input }}">
                    @error('keep_daily')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror</div>
                <div><label class="block text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_keep_weekly') }}</label>
                    <input type="number" name="keep_weekly" min="0" value="{{ old('keep_weekly', $tiers['weekly']) }}" class="{{ $input }}"></div>
                <div><label class="block text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_keep_monthly') }}</label>
                    <input type="number" name="keep_monthly" min="0" value="{{ old('keep_monthly', $tiers['monthly']) }}" class="{{ $input }}"></div>
            </div>
        </div>
        @php $sel = (array) old('notify_channels', $j->notify_channels ?? []); @endphp
        <div class="sm:col-span-2">
            <span class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_notify') }}</span>
            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                @foreach (['desktop' => __('settings.backup_notify_desktop'), 'mail' => __('settings.notify_mail_heading'), 'ntfy' => 'NTFY', 'webhook' => 'Webhook'] as $ch => $label)
                    <label class="flex items-center gap-2 text-sm text-md-on-surface-var dark:text-md-on-surface-var">
                        <input type="checkbox" name="notify_channels[]" value="{{ $ch }}" @checked(in_array($ch, $sel, true))
                            @if ($ch === 'desktop') @change="if ($event.target.checked && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission()" @endif
                            class="rounded border-md-outline-variant dark:border-md-outline-variant focus:ring-accent">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-start gap-6">
        <label class="flex items-center gap-2 text-sm text-md-on-surface-var dark:text-md-on-surface-var">
            <input type="checkbox" name="encrypt" value="1" x-model="encrypt" class="rounded border-md-outline-variant dark:border-md-outline-variant focus:ring-accent">
            {{ __('settings.backup_encrypt') }}</label>
        <label class="flex items-center gap-2 text-sm text-md-on-surface-var dark:text-md-on-surface-var">
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $j->enabled ?? true)) class="rounded border-md-outline-variant dark:border-md-outline-variant focus:ring-accent">
            {{ __('settings.backup_enabled') }}</label>
        <div x-show="encrypt" x-cloak class="min-w-[16rem]">
            <label class="block text-sm font-medium text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.backup_passphrase') }}</label>
            <input type="password" name="passphrase" value="" class="{{ $input }}" autocomplete="new-password" placeholder="{{ $j ? '••••••••' : '' }}">
            @if ($j)<p class="mt-1 text-xs text-md-on-surface-var dark:text-md-on-surface-var">{{ __('settings.notify_secret_keep_hint') }}</p>@endif
            @error('passphrase')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>
    <x-button variant="primary" type="submit">{{ __('settings.backup_save') }}</x-button>
</form>
