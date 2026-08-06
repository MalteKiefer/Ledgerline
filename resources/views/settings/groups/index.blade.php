<x-layouts.app :title="__('settings.groups_section')">
    <div class="mx-auto w-full max-w-[1700px]" x-data="{ open: null }">
        @include('profile._header', ['title' => __('settings.groups_section'), 'subtitle' => __('settings.groups_desc')])

        @if (session('status'))
            <div class="mt-4 rounded-xl border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-700 dark:text-green-300" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-4 rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- Create a group --}}
        <div class="mt-5 ll-card">
            <button type="button" @click="open = (open === 'new' ? null : 'new')" class="flex w-full items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('settings.groups_create') }}</h2>
                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400 transition" ::class="open === 'new' ? 'rotate-90' : ''" />
            </button>
            <form x-show="open === 'new'" x-cloak method="POST" action="{{ route('settings.groups.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @csrf
                @include('settings.groups._fields', ['group' => null])
                <div class="sm:col-span-2"><x-button type="submit">{{ __('settings.groups_create') }}</x-button></div>
            </form>
        </div>

        {{-- Group list --}}
        <div class="mt-5 space-y-3">
            @forelse ($groups as $g)
                <div class="ll-card">
                    <div class="flex items-center gap-3">
                        <span class="ll-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#3fae9f"><x-icon name="user-group" class="h-4 w-4 text-white" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                <span class="truncate">{{ $g->name }}</span>
                                @if ($g->shareable)<x-badge variant="accent">{{ __('settings.groups_shareable') }}</x-badge>@endif
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                                <span>{{ trans_choice('settings.groups_member_count', $g->members_count, ['count' => $g->members_count]) }}</span>
                                @if ($g->files_quota_mb)<span>{{ __('settings.users_quota_files') }}: {{ $g->files_quota_mb }} MB</span>@endif
                                @if ($g->max_connected_devices)<span>{{ __('settings.users_devices') }}: {{ $g->max_connected_devices }}</span>@endif
                            </div>
                        </div>
                        <x-icon-button name="pencil" tone="gray" size="sm" class="shrink-0" @click="open = (open === {{ $g->id }} ? null : {{ $g->id }})" :aria-label="__('common.edit')" />
                    </div>
                    <div x-show="open === {{ $g->id }}" x-cloak class="mt-4 border-t border-black/[0.06] dark:border-white/10 pt-4">
                        <form method="POST" action="{{ route('settings.groups.update', $g) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @csrf @method('PUT')
                            @include('settings.groups._fields', ['group' => $g])
                            <div class="sm:col-span-2"><x-button type="submit">{{ __('settings.save') }}</x-button></div>
                        </form>
                        <div class="mt-2">
                            <form method="POST" action="{{ route('settings.groups.destroy', $g) }}"
                                  x-on:submit="if (! confirm(@js(__('settings.groups_delete_confirm')))) $event.preventDefault()">
                                @csrf @method('DELETE')
                                <x-button variant="danger" size="sm" type="submit">{{ __('settings.groups_delete') }}</x-button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('settings.groups_empty') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
