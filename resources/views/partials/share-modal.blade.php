{{-- Reusable share dialog. The host Alpine component provides shareModal state
     + openShare/loadShares/addShare/revokeShare and cfg.shares* URLs. --}}
<div x-show="shareModal.open" x-cloak class="fixed inset-0 z-[75] flex items-start justify-center overflow-y-auto p-4"
    role="dialog" aria-modal="true" @keydown.escape.window="shareModal.open=false">
    <div class="absolute inset-0 bg-gray-900/40" @click="shareModal.open=false"></div>
    <div class="relative my-16 w-full max-w-md rounded-lg bg-white dark:bg-gray-900 p-5 shadow-xl">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="'{{ __('shares.title', ['name' => '__N__']) }}'.replace('__N__', shareModal.name)"></h3>

        <form @submit.prevent="addShare()" class="mt-4 flex flex-wrap items-end gap-2">
            <input type="email" x-model="shareModal.email" required placeholder="{{ __('shares.email_placeholder') }}"
                class="min-w-0 flex-1 rounded-md border-gray-300 dark:border-gray-700 text-sm">
            <select x-model="shareModal.permission" class="rounded-md border-gray-300 dark:border-gray-700 text-sm">
                <option value="read">{{ __('shares.permission_read') }}</option>
                <option value="write">{{ __('shares.permission_write') }}</option>
            </select>
            <x-button variant="primary" type="submit">{{ __('shares.add') }}</x-button>
        </form>
        <p x-show="shareModal.error" x-cloak class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="shareModal.error"></p>

        <div class="mt-3 flex items-center gap-2">
            <x-button variant="secondary" icon="link" @click="copyShareLink()">{{ __('shares.copy_link') }}</x-button>
            <span x-show="shareModal.feedback" x-cloak x-transition class="text-xs font-medium text-green-600" x-text="shareModal.feedback"></span>
        </div>

        <div class="mt-5">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('shares.shared_heading') }}</h4>
            <p x-show="!shareModal.shares.length" x-cloak class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('shares.no_shares') }}</p>
            <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                <template x-for="s in shareModal.shares" :key="s.id">
                    <li class="flex items-center justify-between gap-2 py-2 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-gray-900 dark:text-gray-100" x-text="s.user.name || s.user.email"></span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="s.permission === 'write' ? '{{ __('shares.permission_write') }}' : '{{ __('shares.permission_read') }}'"></span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <button type="button" x-show="shareMailConfigured" @click="emailShare(s.id)" class="text-xs font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">{{ __('shares.send_mail') }}</button>
                            <button type="button" @click="revokeShare(s.id)" class="text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">{{ __('shares.revoke') }}</button>
                        </span>
                    </li>
                </template>
            </ul>
        </div>

        {{-- External: public tokenised link (no account needed) --}}
        <div class="mt-5 border-t border-gray-100 dark:border-gray-800 pt-4">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('shares.public_heading') }}</h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('shares.public_hint') }}</p>

            {{-- Expiry + (album-only) password, applied on create and on update --}}
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <label class="flex-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('shares.public_expiry_label') }}
                    <select x-model="shareModal.publicExpiry" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-xs">
                        <option value="">{{ __('shares.public_expiry_never') }}</option>
                        <option value="3600">{{ __('shares.public_expiry_1h') }}</option>
                        <option value="86400">{{ __('shares.public_expiry_24h') }}</option>
                        <option value="604800">{{ __('shares.public_expiry_7d') }}</option>
                        <option value="2592000">{{ __('shares.public_expiry_30d') }}</option>
                    </select>
                </label>
                <label class="flex-1 text-xs text-gray-500 dark:text-gray-400" x-show="shareModal.type === 'albums'">
                    {{ __('shares.public_password_label') }}
                    <input type="text" x-model="shareModal.publicPassword" autocomplete="off"
                        :placeholder="shareModal.publicHasPassword ? '••••••' : ''"
                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 text-xs">
                </label>
            </div>

            <template x-if="!shareModal.publicUrl">
                <x-button variant="secondary" icon="link" class="mt-2" @click="createPublic()">{{ __('shares.public_create') }}</x-button>
            </template>
            <template x-if="shareModal.publicUrl">
                <div class="mt-2 space-y-2">
                    <div class="flex items-center gap-2">
                        <input readonly :value="shareModal.publicUrl" @focus="$event.target.select()" class="min-w-0 flex-1 rounded-md border-gray-300 dark:border-gray-700 text-xs">
                        <x-button variant="secondary" icon="link" @click="copyPublicLink()">{{ __('shares.copy_link') }}</x-button>
                    </div>
                    <p x-show="shareModal.publicExpiresAt" x-cloak class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('shares.public_expiry_label') }}: <span x-text="shareModal.publicExpiresAt ? new Date(shareModal.publicExpiresAt).toLocaleString() : ''"></span>
                    </p>
                    <div x-show="shareMailConfigured" class="flex items-center gap-2">
                        <input type="email" x-model="shareModal.publicEmail" placeholder="{{ __('shares.public_email_placeholder') }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-xs">
                        <x-button variant="secondary" @click="emailPublic()">{{ __('shares.send_mail') }}</x-button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-button variant="secondary" icon="arrow-path" @click="createPublic()">{{ __('common.save') }}</x-button>
                        <button type="button" @click="rotatePublic('{{ __('shares.public_rotated') }}')" class="text-xs font-medium text-gray-600 hover:text-gray-900">{{ __('shares.public_rotate') }}</button>
                        <button type="button" @click="revokePublic()" class="text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300">{{ __('shares.public_revoke') }}</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-5 flex justify-end">
            <x-button variant="secondary" @click="shareModal.open=false">{{ __('common.cancel') }}</x-button>
        </div>
    </div>
</div>
