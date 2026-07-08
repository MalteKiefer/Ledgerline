{{-- Zero-knowledge vault: setup / unlock / recover. All crypto happens in the
     browser via the $store.vault store; the server only holds ciphertext. --}}
<div x-data="{
        open: false,
        mode: 'unlock',
        pass: '', pass2: '', code: '', recovery: '', error: '', busy: false,
        init() {
            // No vault yet: prompt setup automatically, once per tab session.
            @if (! ($serverConfigured ?? true))
                if (! sessionStorage.getItem('vault.setup-prompted')) {
                    sessionStorage.setItem('vault.setup-prompted', '1');
                    this.mode = 'setup';
                    this.open = true;
                }
            @endif
        },
        panel() {
            this.mode = $store.vault.configured ? 'unlock' : 'setup';
            this.pass = this.pass2 = this.code = this.recovery = this.error = '';
            this.open = true;
        },
        async doSetup() {
            this.error = '';
            if (this.pass.length < 10) { this.error = '{{ __('vault.err_short') }}'; return; }
            if (this.pass !== this.pass2) { this.error = '{{ __('vault.err_mismatch') }}'; return; }
            this.busy = true;
            try { this.recovery = await $store.vault.setup(this.pass); this.mode = 'recovery'; }
            catch (e) { this.error = '{{ __('vault.err_generic') }}'; }
            finally { this.busy = false; }
        },
        async doUnlock() {
            this.error = ''; this.busy = true;
            try { await $store.vault.unlock(this.pass); this.open = false; }
            catch (e) { this.error = '{{ __('vault.err_wrong') }}'; }
            finally { this.busy = false; }
        },
        async doRecover() {
            this.error = ''; this.busy = true;
            // Recovery unlocks the in-memory key; then FORCE a new passphrase (the
            // forgotten one is unknown) — this also mints a fresh recovery code.
            try { await $store.vault.recover(this.code); this.pass = this.pass2 = ''; this.mode = 'setnew'; }
            catch (e) { this.error = '{{ __('vault.err_recover') }}'; }
            finally { this.busy = false; }
        },
        async doSetNew() {
            this.error = '';
            if (this.pass.length < 10) { this.error = '{{ __('vault.err_short') }}'; return; }
            if (this.pass !== this.pass2) { this.error = '{{ __('vault.err_mismatch') }}'; return; }
            this.busy = true;
            try { this.recovery = await $store.vault.setPassphrase(this.pass); this.mode = 'recovery'; }
            catch (e) { this.error = '{{ __('vault.err_change') }}'; }
            finally { this.busy = false; }
        },
        recoverPanel() {
            this.mode = 'recover';
            this.pass = this.pass2 = this.code = this.recovery = this.error = '';
            this.open = true;
        },
        changePanel() {
            this.mode = 'change';
            this.pass = this.pass2 = this.code = this.recovery = this.error = '';
            this.open = true;
        },
        async doChange() {
            this.error = '';
            if (this.pass2.length < 10) { this.error = '{{ __('vault.err_short') }}'; return; }
            this.busy = true;
            // A passphrase change also mints a fresh recovery code, shown once.
            try { this.recovery = await $store.vault.changePassphrase(this.pass, this.pass2); this.mode = 'recovery'; }
            catch (e) { this.error = '{{ __('vault.err_change') }}'; }
            finally { this.busy = false; }
        },
     }" @vault-panel.window="panel()" @vault-change.window="changePanel()" @vault-recover.window="recoverPanel()">

    {{-- Monochrome padlock: open = unlocked, closed = locked / not set up. --}}
    {{-- No inline padlock here: the Files header renders the lock toggle next to
         Upload and opens this panel via the vault-panel window event. --}}

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="open = false"></div>
            <div class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">

                {{-- Set up --}}
                <template x-if="mode === 'setup'">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('vault.setup_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('vault.setup_hint') }}</p>
                        <input type="password" x-model="pass" placeholder="{{ __('vault.passphrase') }}" class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="password" x-model="pass2" placeholder="{{ __('vault.passphrase_confirm') }}" class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('vault.warning') }}</p>
                        <div class="mt-5 flex justify-end gap-3">
                            <button type="button" @click="open = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                            <button type="button" @click="doSetup()" :disabled="busy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('vault.create') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Recovery code (shown once) --}}
                <template x-if="mode === 'recovery'">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('vault.recovery_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('vault.recovery_hint') }}</p>
                        <pre class="mt-3 select-all whitespace-pre-wrap break-all rounded-md bg-gray-100 p-3 font-mono text-sm text-gray-900" x-text="recovery"></pre>
                        <div class="mt-5 flex justify-end">
                            <button type="button" @click="open = false" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">{{ __('vault.saved_it') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Unlock --}}
                <template x-if="mode === 'unlock'">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('vault.unlock_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('vault.unlock_hint') }}</p>
                        <input type="password" x-model="pass" @keydown.enter="doUnlock()" placeholder="{{ __('vault.passphrase') }}" class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                        <div class="mt-5 flex items-center justify-between">
                            <button type="button" @click="mode = 'recover'; error = ''" class="text-sm text-gray-500 hover:text-gray-900">{{ __('vault.forgot') }}</button>
                            <button type="button" @click="doUnlock()" :disabled="busy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('vault.unlock') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Change passphrase --}}
                <template x-if="mode === 'change'">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('vault.change_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('vault.change_hint') }}</p>
                        <input type="password" x-model="pass" placeholder="{{ __('vault.change_current') }}" class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="password" x-model="pass2" @keydown.enter="doChange()" placeholder="{{ __('vault.change_new') }}" class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                        <div class="mt-5 flex justify-end gap-3">
                            <button type="button" @click="open = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('common.cancel') }}</button>
                            <button type="button" @click="doChange()" :disabled="busy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('vault.change') }}</button>
                        </div>
                    </div>
                </template>

                {{-- Recover --}}
                <template x-if="mode === 'recover'">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('vault.recover_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('vault.recover_hint') }}</p>
                        <textarea x-model="code" rows="2" class="mt-4 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500"></textarea>
                        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                        <div class="mt-5 flex items-center justify-between">
                            <button type="button" @click="mode = 'unlock'; error = ''" class="text-sm text-gray-500 hover:text-gray-900">{{ __('common.cancel') }}</button>
                            <button type="button" @click="doRecover()" :disabled="busy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('vault.restore') }}</button>
                        </div>
                    </div>
                </template>

                {{-- After a recovery unlock: set a NEW passphrase (the old one is unknown). --}}
                <template x-if="mode === 'setnew'">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('vault.setnew_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ __('vault.setnew_hint') }}</p>
                        <input type="password" x-model="pass" placeholder="{{ __('vault.change_new') }}" class="mt-4 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <input type="password" x-model="pass2" @keydown.enter="doSetNew()" placeholder="{{ __('vault.passphrase_confirm') }}" class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                        <div class="mt-5 flex justify-end">
                            <button type="button" @click="doSetNew()" :disabled="busy" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">{{ __('vault.change') }}</button>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </template>
</div>
