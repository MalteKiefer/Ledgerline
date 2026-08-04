<x-layouts.app :title="__('mailkeys.title')">
    <div class="mx-auto w-full max-w-[1700px]" x-data="mailKeys({
        errNotPrivate: @js(__('mailkeys.err_not_private')),
        errImport: @js(__('mailkeys.err_import')),
        errGenerate: @js(__('mailkeys.err_generate')),
        errNoIdentity: @js(__('mailkeys.err_no_identity')),
        errLocked: @js(__('mailkeys.err_locked')),
        filesRawBase: '{{ url('/files/raw') }}',
        confirmDelete: @js(__('mailkeys.confirm_delete')),
        copied: @js(__('mailkeys.copied')),
     })">
        @include('profile._header', ['title' => __('mailkeys.title')])

        <p class="mt-4 mb-4 px-1 text-sm text-gray-500 dark:text-gray-400">{{ __('mailkeys.subtitle') }}</p>

        {{-- Vault locked --}}
        <template x-if="state === 'locked'">
            <x-alert variant="info">
                <span>{{ __('mailkeys.locked_hint') }}</span>
                <button type="button" class="ml-1 font-medium text-accent underline" @click="unlock()">{{ __('mailkeys.unlock') }}</button>
            </x-alert>
        </template>

        <template x-if="state === 'ready'">
        <div>
            <x-alert variant="error" x-show="error" x-cloak x-text="error" class="mb-4" />

            {{-- Actions --}}
            <div class="mb-4 flex flex-wrap gap-2" x-show="mode === 'list'">
                <x-button variant="secondary" size="sm" icon="plus" @click="openImport()">{{ __('mailkeys.import') }}</x-button>
                <x-button variant="secondary" size="sm" icon="key" @click="mode = 'generate'">{{ __('mailkeys.generate') }}</x-button>
                <x-button variant="secondary" size="sm" icon="lock-closed" @click="mode = 'smime'">{{ __('mailkeys.import_smime') }}</x-button>
                <x-button variant="secondary" size="sm" icon="lock-closed" @click="mode = 'smime-gen'">{{ __('mailkeys.gen_smime') }}</x-button>
            </div>

            {{-- S/MIME import: .p12 OR PEM (key + certificate) --}}
            <div class="ll-card mb-4" x-show="mode === 'smime'" x-cloak>
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.import_smime') }}</h3>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.name') }}</label>
                <input type="text" x-model="smName" class="mt-1 mb-3 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">

                <div class="mb-3 inline-flex rounded-xl bg-black/[0.04] dark:bg-white/[0.06] p-0.5 text-sm">
                    <button type="button" @click="smImpMode = 'p12'" class="rounded-lg px-3 py-1 font-medium transition" :class="smImpMode === 'p12' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('mailkeys.p12_file') }}</button>
                    <button type="button" @click="smImpMode = 'pem'" class="rounded-lg px-3 py-1 font-medium transition" :class="smImpMode === 'pem' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">PEM</button>
                </div>

                {{-- .p12 --}}
                <div x-show="smImpMode === 'p12'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.p12_file') }}</label>
                    <input type="file" accept=".p12,.pfx,application/x-pkcs12" @change="p12Chosen($event)" class="mt-1 mb-3 block w-full text-sm text-gray-600 dark:text-gray-400">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.passphrase_opt') }}</label>
                    <input type="password" x-model="smPass" autocomplete="new-password" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    <div class="flex gap-2">
                        <x-button variant="primary" size="sm" ::disabled="busy" @click="importSmime()">{{ __('mailkeys.add') }}</x-button>
                        <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                    </div>
                </div>

                {{-- PEM (key + cert), paste or file --}}
                <div x-show="smImpMode === 'pem'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.from_file') }}</label>
                    <input type="file" accept=".pem,.crt,.cer,.key,.txt" @change="smPemFileChosen($event)" class="mt-1 mb-3 block w-full text-sm text-gray-600 dark:text-gray-400">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.pem_key_cert') }}</label>
                    <textarea x-model="smPem" rows="7" placeholder="-----BEGIN PRIVATE KEY-----&#10;…&#10;-----BEGIN CERTIFICATE-----" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 font-mono text-xs"></textarea>
                    <div class="flex gap-2">
                        <x-button variant="primary" size="sm" ::disabled="busy || !smPem" @click="importSmimePemNow()">{{ __('mailkeys.add') }}</x-button>
                        <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                    </div>
                </div>
            </div>

            {{-- S/MIME generate (self-signed) --}}
            <div class="ll-card mb-4" x-show="mode === 'smime-gen'" x-cloak>
                <h3 class="mb-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.gen_smime') }}</h3>
                <p class="mb-3 text-xs text-gray-400 dark:text-gray-500">{{ __('mailkeys.gen_smime_hint') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.name') }}</label>
                        <input type="text" x-model="smGenCn" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.email') }}</label>
                        <input type="email" x-model="smGenEmail" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.rsa_bits') }}</label>
                        <select x-model="smGenBits" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                            <option value="2048">2048</option>
                            <option value="3072">3072</option>
                            <option value="4096">4096</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.expiry') }}</label>
                        <select x-model="smGenExpiry" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                            <option value="365">{{ __('mailkeys.expiry_1y') }}</option>
                            <option value="730">{{ __('mailkeys.expiry_2y') }}</option>
                            <option value="1095">{{ __('mailkeys.expiry_3y') }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <x-button variant="primary" size="sm" ::disabled="busy" @click="generateSmimeNow()">
                        <span x-show="!busy">{{ __('mailkeys.generate') }}</span>
                        <span x-show="busy" x-cloak><x-icon name="arrow-path" class="mr-1 inline h-4 w-4 animate-spin" />{{ __('mailkeys.generating') }}</span>
                    </x-button>
                    <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                </div>
            </div>

            {{-- Import modal — tabs: paste / from computer / from app files --}}
            <template x-teleport="body">
            <div x-show="mode === 'import'" x-cloak class="fixed inset-0 z-[1080] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="mode = 'list'; error = ''">
                <div class="absolute inset-0 bg-gray-900/50" @click="mode = 'list'; error = ''"></div>
                <div class="relative flex max-h-[85vh] w-[50vw] max-w-[50vw] flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.import') }}</h3>
                        <x-icon-button name="x-mark" tone="gray" size="sm" @click="mode = 'list'; error = ''" :aria-label="__('common.close')" />
                    </div>

                    <div class="min-h-0 flex-1 overflow-auto p-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.name') }}</label>
                        <input type="text" x-model="impName" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">

                        {{-- Tabs --}}
                        <div class="mb-4 inline-flex rounded-xl bg-black/[0.04] dark:bg-white/[0.06] p-0.5 text-sm">
                            <button type="button" @click="impMode = 'paste'" class="rounded-lg px-3 py-1 font-medium transition" :class="impMode === 'paste' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('mailkeys.tab_paste') }}</button>
                            <button type="button" @click="impMode = 'computer'" class="rounded-lg px-3 py-1 font-medium transition" :class="impMode === 'computer' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('mailkeys.tab_computer') }}</button>
                            <button type="button" @click="impMode = 'app'; loadAppFiles()" class="rounded-lg px-3 py-1 font-medium transition" :class="impMode === 'app' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-500'">{{ __('mailkeys.tab_app') }}</button>
                        </div>

                        {{-- Paste --}}
                        <div x-show="impMode === 'paste'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.armored_private') }}</label>
                            <textarea x-model="impArmored" rows="8" placeholder="-----BEGIN PGP PRIVATE KEY BLOCK-----" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 font-mono text-xs"></textarea>
                        </div>

                        {{-- From computer --}}
                        <div x-show="impMode === 'computer'" x-cloak>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.from_file') }}</label>
                            <input type="file" accept=".asc,.gpg,.key,.pgp,.txt,.pem,application/pgp-keys" @change="impFileChosen($event)" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-400">
                            <p x-show="impArmored" x-cloak class="mt-2 text-xs text-green-600 dark:text-green-400"><x-icon name="check" class="mr-1 inline h-3 w-3" /><span x-text="impName"></span></p>
                        </div>

                        {{-- From app files — a real file browser over the vault Files tree --}}
                        <div x-show="impMode === 'app'" x-cloak>
                            <x-alert variant="error" x-show="appFileError" x-cloak x-text="appFileError" class="mb-2" />
                            <p x-show="appFilesLoading" class="py-4 text-center text-sm text-gray-500">{{ __('common.loading') }}</p>

                            <div x-show="!appFilesLoading" class="rounded-xl border border-black/[0.06] dark:border-white/10">
                                {{-- Breadcrumb --}}
                                <div class="flex items-center gap-1 overflow-x-auto border-b border-black/[0.06] dark:border-white/10 px-3 py-2 text-sm">
                                    <button type="button" @click="browserGoto(-1)" class="shrink-0 font-medium text-accent hover:underline">{{ __('mailkeys.files_root') }}</button>
                                    <template x-for="(crumb, i) in browserPath" :key="crumb.id">
                                        <span class="flex shrink-0 items-center gap-1">
                                            <x-icon name="chevron-right" class="h-3 w-3 text-gray-400" />
                                            <button type="button" @click="browserGoto(i)" class="text-gray-600 dark:text-gray-300 hover:underline" x-text="crumb.name"></button>
                                        </span>
                                    </template>
                                </div>
                                {{-- Listing: folders first, then files --}}
                                <div class="max-h-64 overflow-auto divide-y divide-black/[0.06] dark:divide-white/10">
                                    <template x-for="d in browserFolders()" :key="d.id">
                                        <button type="button" @click="enterFolder(d)" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent/5">
                                            <x-icon name="folder" class="h-4 w-4 shrink-0 text-accent" />
                                            <span class="min-w-0 flex-1 truncate font-medium" x-text="d.name"></span>
                                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
                                        </button>
                                    </template>
                                    <template x-for="f in browserFiles()" :key="f.id">
                                        <button type="button" @click="pickAppFile(f)" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent/5">
                                            <x-icon name="document-text" class="h-4 w-4 shrink-0 text-gray-400" />
                                            <span class="min-w-0 flex-1 truncate" x-text="f.name"></span>
                                        </button>
                                    </template>
                                    <p x-show="browserFolders().length === 0 && browserFiles().length === 0" class="px-3 py-6 text-center text-sm text-gray-400">{{ __('mailkeys.no_files') }}</p>
                                </div>
                            </div>
                            <p x-show="impArmored" x-cloak class="mt-2 text-xs text-green-600 dark:text-green-400"><x-icon name="check" class="mr-1 inline h-3 w-3" /><span x-text="impName"></span></p>
                        </div>

                        <label class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.passphrase_opt') }}</label>
                        <input type="password" x-model="impPassphrase" autocomplete="new-password" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-gray-100 dark:border-gray-800 px-5 py-3">
                        <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                        <x-button variant="primary" size="sm" ::disabled="busy || !impArmored" @click="importKey()">{{ __('mailkeys.add') }}</x-button>
                    </div>
                </div>
            </div>
            </template>

            {{-- Generate form — full configuration spectrum --}}
            <div class="ll-card mb-4" x-show="mode === 'generate'" x-cloak>
                <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('mailkeys.generate') }}</h3>

                {{-- Identities (multiple user IDs) --}}
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.identities') }}</label>
                <p class="mb-2 text-xs text-gray-400 dark:text-gray-500">{{ __('mailkeys.identities_hint') }}</p>
                <template x-for="(uid, i) in genUserIDs" :key="i">
                    <div class="mb-2 flex items-center gap-2">
                        <input type="text" x-model="uid.name" placeholder="{{ __('mailkeys.name') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                        <input type="email" x-model="uid.email" placeholder="{{ __('mailkeys.email') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                        <x-icon-button name="trash" tone="red" size="sm" x-show="genUserIDs.length > 1" @click="removeUserId(i)" :aria-label="__('common.delete')" />
                    </div>
                </template>
                <button type="button" class="mb-4 text-xs font-medium text-accent" @click="addUserId()">+ {{ __('mailkeys.add_identity') }}</button>

                {{-- Algorithm --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.algorithm') }}</label>
                        <select x-model="genType" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                            <option value="ecc">ECC (Curve)</option>
                            <option value="rsa">RSA</option>
                        </select>
                    </div>
                    <div x-show="genType === 'ecc'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.curve') }}</label>
                        <select x-model="genCurve" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                            <option value="curve25519">Curve25519 (Ed25519)</option>
                            <option value="nistP256">NIST P-256</option>
                            <option value="nistP384">NIST P-384</option>
                            <option value="nistP521">NIST P-521</option>
                            <option value="brainpoolP256r1">Brainpool P-256r1</option>
                            <option value="brainpoolP384r1">Brainpool P-384r1</option>
                            <option value="brainpoolP512r1">Brainpool P-512r1</option>
                        </select>
                    </div>
                    <div x-show="genType === 'rsa'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.rsa_bits') }}</label>
                        <select x-model="genRsaBits" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                            <option value="2048">2048</option>
                            <option value="3072">3072</option>
                            <option value="4096">4096</option>
                        </select>
                    </div>
                </div>

                {{-- Expiry --}}
                <label class="mt-3 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.expiry') }}</label>
                <select x-model="genExpiry" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">
                    <option value="0">{{ __('mailkeys.expiry_never') }}</option>
                    <option value="31536000">{{ __('mailkeys.expiry_1y') }}</option>
                    <option value="63072000">{{ __('mailkeys.expiry_2y') }}</option>
                    <option value="94608000">{{ __('mailkeys.expiry_3y') }}</option>
                </select>

                {{-- Subkeys --}}
                <label class="mt-3 flex items-start gap-2">
                    <input type="checkbox" x-model="genSignSubkey" class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-accent focus:ring-accent">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('mailkeys.sign_subkey') }}</span>
                </label>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('mailkeys.subkeys_hint') }}</p>

                {{-- Passphrase --}}
                <label class="mt-3 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('mailkeys.passphrase_opt') }}</label>
                <input type="password" x-model="genPassphrase" autocomplete="new-password" class="mt-1 mb-4 block w-full rounded-md border-gray-300 dark:border-gray-700 sm:text-sm">

                <div class="flex items-center gap-2">
                    <x-button variant="primary" size="sm" ::disabled="busy" @click="generate()">
                        <span x-show="!busy">{{ __('mailkeys.generate') }}</span>
                        <span x-show="busy" x-cloak><x-icon name="arrow-path" class="mr-1 inline h-4 w-4 animate-spin" />{{ __('mailkeys.generating') }}</span>
                    </x-button>
                    <x-button variant="secondary" size="sm" @click="mode = 'list'; error = ''">{{ __('common.cancel') }}</x-button>
                </div>
            </div>

            {{-- Key list --}}
            <x-empty-state icon="key" x-show="mode === 'list' && keys.length === 0" class="py-10">{{ __('mailkeys.no_keys') }}</x-empty-state>

            <div class="ll-card !p-0 overflow-hidden divide-y divide-black/[0.06] dark:divide-white/10" x-show="keys.length > 0">
                <template x-for="k in keys" :key="k.id">
                    <div class="flex items-start gap-3 px-4 py-3">
                        <span class="ll-chip mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#7066f5">
                            <x-icon name="key" class="h-4 w-4 text-white" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                <span class="truncate" x-text="k.name"></span>
                                <x-badge variant="gray" x-text="keyType(k)"></x-badge>
                            </p>
                            <p class="truncate text-xs text-gray-500" x-text="k.userId"></p>
                            <p class="mt-0.5 font-mono text-[11px] text-gray-400" x-text="fp(k)"></p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <x-icon-button name="clipboard" tone="gray" size="sm" @click="copyPublic(k)" :aria-label="__('mailkeys.copy_public')" />
                            <x-icon-button name="trash" tone="red" size="sm" @click="removeKey(k.id)" :aria-label="__('common.delete')" />
                        </div>
                    </div>
                </template>
            </div>
        </div>
        </template>
    </div>
</x-layouts.app>
