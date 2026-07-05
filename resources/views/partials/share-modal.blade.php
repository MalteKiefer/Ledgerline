{{-- Reusable share dialog. The host Alpine component provides shareModal state
     + openShare/loadShares/addShare/revokeShare and cfg.shares* URLs. --}}
<div x-show="shareModal.open" x-cloak class="fixed inset-0 z-[75] flex items-start justify-center overflow-y-auto p-4"
    role="dialog" aria-modal="true" @keydown.escape.window="shareModal.open=false">
    <div class="absolute inset-0 bg-gray-900/40" @click="shareModal.open=false"></div>
    <div class="relative my-16 w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900" x-text="'{{ __('shares.title', ['name' => '__N__']) }}'.replace('__N__', shareModal.name)"></h3>

        <form @submit.prevent="addShare()" class="mt-4 flex flex-wrap items-end gap-2">
            <input type="email" x-model="shareModal.email" required placeholder="{{ __('shares.email_placeholder') }}"
                class="min-w-0 flex-1 rounded-md border-gray-300 text-sm">
            <select x-model="shareModal.permission" class="rounded-md border-gray-300 text-sm">
                <option value="read">{{ __('shares.permission_read') }}</option>
                <option value="write">{{ __('shares.permission_write') }}</option>
            </select>
            <x-button variant="primary" type="submit">{{ __('shares.add') }}</x-button>
        </form>
        <p x-show="shareModal.error" x-cloak class="mt-1 text-xs text-red-600" x-text="shareModal.error"></p>

        <div class="mt-3 flex items-center gap-2">
            <x-button variant="secondary" icon="link" @click="copyShareLink()">{{ __('shares.copy_link') }}</x-button>
            <span x-show="shareModal.feedback" x-cloak x-transition class="text-xs font-medium text-green-600" x-text="shareModal.feedback"></span>
        </div>

        <div class="mt-5">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('shares.shared_heading') }}</h4>
            <p x-show="!shareModal.shares.length" x-cloak class="mt-2 text-sm text-gray-500">{{ __('shares.no_shares') }}</p>
            <ul class="mt-2 divide-y divide-gray-100">
                <template x-for="s in shareModal.shares" :key="s.id">
                    <li class="flex items-center justify-between gap-2 py-2 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-gray-900" x-text="s.user.name || s.user.email"></span>
                            <span class="block text-xs text-gray-500" x-text="s.permission === 'write' ? '{{ __('shares.permission_write') }}' : '{{ __('shares.permission_read') }}'"></span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <button type="button" x-show="shareMailConfigured" @click="emailShare(s.id)" class="text-xs font-medium text-gray-600 hover:text-gray-900">{{ __('shares.send_mail') }}</button>
                            <button type="button" @click="revokeShare(s.id)" class="text-xs font-medium text-red-600 hover:text-red-700">{{ __('shares.revoke') }}</button>
                        </span>
                    </li>
                </template>
            </ul>
        </div>

        <div class="mt-5 flex justify-end">
            <x-button variant="secondary" @click="shareModal.open=false">{{ __('common.cancel') }}</x-button>
        </div>
    </div>
</div>
