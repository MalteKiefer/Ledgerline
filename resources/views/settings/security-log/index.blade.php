<x-layouts.app :title="__('settings.seclog_title')">
    <x-page-heading :title="__('settings.seclog_title')" :subtitle="__('settings.seclog_subtitle')" />

    {{-- Filters + export --}}
    <form method="GET" action="{{ route('settings.security-log') }}" class="mt-6 ll-card">
        <div class="grid gap-3 sm:grid-cols-4">
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('settings.seclog_filter_action') }}</span>
                <select name="action" class="w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
                    <option value="">{{ __('settings.seclog_filter_action_all') }}</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected($filters['action'] === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('settings.seclog_filter_user') }}</span>
                <input type="number" name="user" value="{{ $filters['user'] }}" min="1"
                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('settings.seclog_filter_since') }}</span>
                <input type="date" name="since" value="{{ $filters['since'] }}"
                    class="w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm focus:border-accent focus:ring-accent">
            </label>
            <div class="flex items-end gap-2">
                <x-button variant="primary" type="submit">{{ __('settings.seclog_apply') }}</x-button>
                <a href="{{ route('settings.security-log') }}" class="inline-flex min-h-9 items-center rounded-xl border border-black/[0.08] dark:border-white/10 px-3 text-sm font-medium text-gray-600 dark:text-gray-300 transition hover:border-accent hover:text-accent">{{ __('settings.seclog_reset') }}</a>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 border-t border-black/[0.06] dark:border-white/10 pt-3">
            <button type="submit" name="export" value="csv" class="inline-flex items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 transition hover:border-accent hover:text-accent"><x-icon name="arrow-down-tray" class="h-4 w-4" />{{ __('settings.seclog_export_csv') }}</button>
            <button type="submit" name="export" value="json" class="inline-flex items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 transition hover:border-accent hover:text-accent"><x-icon name="arrow-down-tray" class="h-4 w-4" />{{ __('settings.seclog_export_json') }}</button>
        </div>
    </form>

    {{-- Log table --}}
    <div class="mt-6 ll-card !p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-black/[0.06] dark:border-white/10 text-xs text-gray-400 dark:text-gray-500">
                        <th class="px-4 py-2.5 font-medium">{{ __('settings.seclog_col_when') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('settings.seclog_col_user') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('settings.seclog_col_action') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('settings.seclog_col_ip') }}</th>
                        <th class="px-4 py-2.5 font-medium">{{ __('settings.seclog_col_meta') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-black/[0.04] dark:border-white/[0.06]">
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $log->actor?->name ?? ($log->user_id ?? '—') }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5"><code class="rounded bg-black/5 dark:bg-white/10 px-1.5 py-0.5 text-xs text-accent">{{ $log->action }}</code></td>
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $log->ip ?? '' }}</td>
                            <td class="max-w-md truncate px-4 py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400" title="{{ json_encode($log->meta) }}">{{ $log->meta ? json_encode($log->meta, JSON_UNESCAPED_SLASHES) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">{{ __('settings.seclog_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($logs->hasPages())
        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-layouts.app>
