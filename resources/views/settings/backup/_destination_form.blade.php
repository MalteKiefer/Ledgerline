@php
    $d = $destination ?? null;
    $cfg = $d?->config ?? [];
    $input = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm';
@endphp
<form method="POST" action="{{ $action }}" x-data="{ driver: '{{ old('driver', $d->driver ?? 's3') }}' }" class="space-y-3">
    @csrf
    @if ($d) @method('PUT') <input type="hidden" name="destination_id" value="{{ $d->id }}"> @endif
    <div class="grid gap-3 sm:grid-cols-2">
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_name') }}</label>
            <input type="text" name="name" value="{{ old('name', $d->name ?? '') }}" required class="{{ $input }}"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_driver') }}</label>
            <select name="driver" x-model="driver" class="{{ $input }}">
                <option value="s3">S3</option>
                <option value="b2">Backblaze B2</option>
                <option value="sftp">SFTP</option>
                <option value="webdav">WebDAV</option>
            </select></div>
    </div>

    {{-- S3 / B2 --}}
    <div x-show="driver === 's3' || driver === 'b2'" class="grid gap-3 sm:grid-cols-2">
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_bucket') }}</label>
            <input type="text" name="bucket" value="{{ old('bucket', $cfg['bucket'] ?? '') }}" class="{{ $input }}"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_region') }}</label>
            <input type="text" name="region" value="{{ old('region', $cfg['region'] ?? '') }}" class="{{ $input }}"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_key') }}</label>
            <input type="text" name="key" value="{{ old('key', $cfg['key'] ?? '') }}" class="{{ $input }}" autocomplete="off"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_secret') }}</label>
            <input type="password" name="secret" value="" class="{{ $input }}" autocomplete="off" placeholder="••••••••">
            @if ($d)<p class="mt-1 text-xs text-gray-500">{{ __('settings.notify_secret_keep_hint') }}</p>@endif</div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_endpoint') }}</label>
            <input type="text" name="endpoint" value="{{ old('endpoint', $cfg['endpoint'] ?? '') }}" class="{{ $input }}"></div>
        <label class="flex items-center gap-2 pt-6 text-sm text-gray-700">
            <input type="checkbox" name="use_path_style" value="1" @checked(old('use_path_style', $cfg['use_path_style'] ?? false)) class="rounded border-gray-300 text-gray-800 focus:ring-gray-500">
            {{ __('settings.backup_use_path_style') }}</label>
    </div>

    {{-- SFTP --}}
    <div x-show="driver === 'sftp'" class="grid gap-3 sm:grid-cols-2">
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_host') }}</label>
            <input type="text" name="host" value="{{ old('host', $cfg['host'] ?? '') }}" class="{{ $input }}"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_port') }}</label>
            <input type="number" name="port" value="{{ old('port', $cfg['port'] ?? 22) }}" class="{{ $input }}"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_username') }}</label>
            <input type="text" name="username" value="{{ old('username', $cfg['username'] ?? '') }}" class="{{ $input }}" autocomplete="off"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_password') }}</label>
            <input type="password" name="password" value="" class="{{ $input }}" autocomplete="off" placeholder="••••••••">
            @if ($d)<p class="mt-1 text-xs text-gray-500">{{ __('settings.notify_secret_keep_hint') }}</p>@endif</div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_path') }}</label>
            <input type="text" name="path" value="{{ old('path', $cfg['path'] ?? '') }}" class="{{ $input }}"></div>
    </div>

    {{-- WebDAV --}}
    <div x-show="driver === 'webdav'" class="grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_base_uri') }}</label>
            <input type="text" name="base_uri" value="{{ old('base_uri', $cfg['base_uri'] ?? '') }}" class="{{ $input }}"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_username') }}</label>
            <input type="text" name="username" value="{{ old('username', $cfg['username'] ?? '') }}" class="{{ $input }}" autocomplete="off"></div>
        <div><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_password') }}</label>
            <input type="password" name="password" value="" class="{{ $input }}" autocomplete="off" placeholder="••••••••">
            @if ($d)<p class="mt-1 text-xs text-gray-500">{{ __('settings.notify_secret_keep_hint') }}</p>@endif</div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700">{{ __('settings.backup_path') }}</label>
            <input type="text" name="path" value="{{ old('path', $cfg['path'] ?? '') }}" class="{{ $input }}"></div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="submit" formaction="{{ route('settings.backup.destinations.test') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('settings.backup_test') }}</button>
        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('settings.backup_save') }}</button>
    </div>
</form>
