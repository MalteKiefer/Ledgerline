<x-layouts.app :title="__('passwords.title')">
  <div x-data="passwords({
        clipboardClearSeconds: 20,
        iconUrl: '{{ url('/passwords/icon') }}',
        breachUrl: '{{ url('/passwords/breach') }}',
     }, {
        copied: @js(__('passwords.copied')),
        iconsDone: @js(__('passwords.icons_done')),
        emptyTrashConfirm: @js(__('passwords.empty_trash_confirm')),
        save: @js(__('passwords.save')),
        folderName: @js(__('passwords.folder_name')),
        deleteFolderConfirm: @js(__('passwords.delete_folder_confirm')),
        titleLabel: @js(__('passwords.title_label')),
        customLabel: @js(__('passwords.custom_changed')),
        types: {
            login: @js(__('passwords.type_login')), password: @js(__('passwords.type_password')),
            card: @js(__('passwords.type_card')), wifi: @js(__('passwords.type_wifi')),
            license: @js(__('passwords.type_license')), server: @js(__('passwords.type_server')),
        },
        fields: {
            username: @js(__('passwords.f_username')), password: @js(__('passwords.f_password')), url: @js(__('passwords.f_url')), urls: @js(__('passwords.f_urls')),
            totp: @js(__('passwords.f_totp')), note: @js(__('passwords.f_note')), cardholder: @js(__('passwords.f_cardholder')),
            number: @js(__('passwords.f_number')), brand: @js(__('passwords.f_brand')), expiry: @js(__('passwords.f_expiry')),
            cvv: @js(__('passwords.f_cvv')), pin: @js(__('passwords.f_pin')), ssid: @js(__('passwords.f_ssid')),
            security: @js(__('passwords.f_security')), hidden: @js(__('passwords.f_hidden')), product: @js(__('passwords.f_product')),
            licensekey: @js(__('passwords.f_licensekey')), owner: @js(__('passwords.f_owner')), email: @js(__('passwords.f_email')),
            host: @js(__('passwords.f_host')), port: @js(__('passwords.f_port')),
        },
     })" @keydown.window="_hotkey($event)">

    {{-- Zero-knowledge gate: secrets decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
        <div class="mx-auto mt-16 max-w-md rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center">
            <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400" x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
            <button type="button" @click="$dispatch('vault-panel')" class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                <x-icon name="lock-open" class="h-4 w-4" /><span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
            </button>
        </div>
    </template>

    <template x-if="state === 'ready'">
      <div>
        <x-page-heading :title="__('passwords.title')">
          <x-slot:actions>
            <div class="flex items-center gap-2">
            <button type="button" @click="refreshAllIcons()" :disabled="iconRefreshing" title="{{ __('passwords.refresh_icons') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-50 dark:text-gray-300 dark:hover:bg-gray-800">
              <span :class="iconRefreshing ? 'animate-spin' : ''"><x-icon name="arrow-path" class="h-4 w-4" /></span>
              <span class="hidden sm:inline" x-text="iconRefreshing ? (iconProgress.done + ' / ' + iconProgress.total) : '{{ __('passwords.refresh_icons') }}'"></span>
            </button>
            <button type="button" @click="openImport()" class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"><x-icon name="arrow-up-tray" class="h-4 w-4" />{{ __('passwords.import') }}</button>
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
              <x-button variant="primary" @click="open = ! open"><x-icon name="plus" class="mr-1.5 h-4 w-4" />{{ __('passwords.new') }}</x-button>
              <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-52 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-1 shadow-lg">
                <template x-for="t in typeList()" :key="t">
                  <button type="button" @click="newItem(t); open = false" class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <span class="text-gray-400">@include('passwords._icon', ['expr' => 't', 'cls' => 'h-4 w-4'])</span>
                    <span x-text="typeLabel(t)"></span>
                  </button>
                </template>
              </div>
            </div>
            </div>
          </x-slot:actions>
        </x-page-heading>

        <div class="mt-6 flex flex-col gap-4 md:flex-row" style="min-height: calc(100vh - 16rem);">
          {{-- Left: filters only (folders, types, tags) --}}
          <aside class="w-full shrink-0 md:w-52">
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2">
              <div class="mb-2 flex flex-wrap gap-1">
                <button type="button" @click="filterType = ''; filterFolder = ''; filterTag = ''; view = 'list'" :class="filterType === '' && filterFolder === '' && filterTag === '' && view === 'list' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'" class="rounded-full px-2.5 py-1 text-xs">{{ __('passwords.all') }}</button>
                <template x-for="t in typeList()" :key="'f' + t">
                  <button type="button" x-show="countOf(t)" @click="filterType = t; view = 'list'" :class="filterType === t && view === 'list' ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'" class="rounded-full px-2.5 py-1 text-xs" x-text="typeLabel(t) + ' ' + countOf(t)"></button>
                </template>
              </div>
              {{-- Folders --}}
              <div class="mb-2 border-t border-gray-100 dark:border-gray-800 pt-2">
                <div class="mb-1 flex items-center justify-between px-1">
                  <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('passwords.folders') }}</span>
                  <button type="button" @click="addFolder()" title="{{ __('passwords.new_folder') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"><x-icon name="folder-plus" class="h-4 w-4" /></button>
                </div>
                <button type="button" @click="filterFolder = filterFolder === '_none' ? '' : '_none'" :class="filterFolder === '_none' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'" class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-xs">
                  <x-icon name="folder" class="h-3.5 w-3.5 text-gray-400" /><span>{{ __('passwords.no_folder') }}</span>
                </button>
                <template x-for="f in folders" :key="f.id">
                  <div class="group flex items-center gap-1 rounded-md pr-1" :class="filterFolder === f.id ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'">
                    <button type="button" @click="filterFolder = filterFolder === f.id ? '' : f.id" class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs" :class="filterFolder === f.id ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400'">
                      <x-icon name="folder" class="h-3.5 w-3.5 shrink-0 text-gray-400" /><span class="truncate" x-text="f.name"></span>
                    </button>
                    <button type="button" @click="renameFolder(f)" class="shrink-0 text-gray-400 opacity-0 hover:text-gray-600 group-hover:opacity-100"><x-icon name="pencil" class="h-3 w-3" /></button>
                    <button type="button" @click="deleteFolder(f)" class="shrink-0 text-gray-400 opacity-0 hover:text-red-600 group-hover:opacity-100"><x-icon name="trash" class="h-3 w-3" /></button>
                  </div>
                </template>
              </div>
              {{-- Tags --}}
              <div x-show="allTags.length" x-cloak class="mb-2 border-t border-gray-100 dark:border-gray-800 pt-2">
                <span class="mb-1 block px-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ __('passwords.tags') }}</span>
                <div class="flex flex-wrap gap-1">
                  <template x-for="t in allTags" :key="'t' + t">
                    <button type="button" @click="filterTag = filterTag === t ? '' : t" :class="filterTag === t ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'" class="rounded-full px-2 py-0.5 text-[11px]" x-text="'#' + t"></button>
                  </template>
                </div>
              </div>
              {{-- Health + Trash --}}
              <div class="border-t border-gray-100 dark:border-gray-800 pt-2">
                <button type="button" @click="view = view === 'health' ? 'list' : 'health'; draft = null" class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-xs" :class="view === 'health' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'">
                  <x-icon name="shield-check" class="h-3.5 w-3.5 text-gray-400" /><span class="flex-1 text-left">{{ __('passwords.health') }}</span>
                  <span x-show="healthCount" x-text="healthCount" class="rounded-full bg-amber-100 dark:bg-amber-900/40 px-1.5 text-[11px] font-medium text-amber-700 dark:text-amber-300"></span>
                </button>
                <button type="button" @click="view = view === 'trash' ? 'list' : 'trash'; current = null; draft = null" class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-xs" :class="view === 'trash' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50'">
                  <x-icon name="trash" class="h-3.5 w-3.5 text-gray-400" /><span class="flex-1 text-left">{{ __('passwords.trash') }}</span>
                  <span x-show="trashCount" x-text="trashCount" class="text-gray-400"></span>
                </button>
                <button type="button" x-show="view === 'trash' && trashCount" @click="emptyTrash()" class="mt-1 w-full rounded-md px-2.5 py-1 text-left text-[11px] font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">{{ __('passwords.empty_trash') }}</button>
              </div>
            </div>
          </aside>

          {{-- Middle: the item list --}}
          <div class="w-full shrink-0 md:w-72">
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-2">
              <div class="relative mb-2">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input type="search" x-model="query" placeholder="{{ __('passwords.search') }}" class="w-full rounded-lg border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 py-2 pl-9 pr-3 text-sm focus:border-gray-400 focus:ring-0">
              </div>
              <div x-show="view === 'health'" x-cloak class="mb-2 flex items-center justify-between gap-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 px-3 py-2">
                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="healthCount ? (healthCount + ' {{ __('passwords.health_issues') }}') : '{{ __('passwords.health_ok') }}'"></span>
                <button type="button" @click="checkBreaches()" :disabled="breachChecking" class="shrink-0 rounded-md bg-gray-900 dark:bg-gray-100 px-2.5 py-1 text-xs font-medium text-white dark:text-gray-900 disabled:opacity-50" x-text="breachChecking ? '{{ __('passwords.checking') }}' : '{{ __('passwords.check_breaches') }}'"></button>
              </div>
              <div class="max-h-[70vh] overflow-y-auto">
                <template x-if="! filtered.length"><p class="px-2 py-6 text-center text-sm text-gray-400">{{ __('passwords.empty') }}</p></template>
                <template x-for="x in filtered" :key="x.id">
                  <button type="button" @click="openItem(x)" :class="current && current.id === x.id ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg"
                          :class="(x.type === 'login' && ! x.icon) ? 'text-xs font-semibold text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500'"
                          :style="(x.type === 'login' && ! x.icon) ? ('background:' + avatarColor(x)) : ''">
                      <template x-if="x.type === 'login' && x.icon"><img :src="x.icon" alt="" class="h-full w-full object-contain"></template>
                      <template x-if="x.type === 'login' && ! x.icon"><span x-text="avatarText(x)"></span></template>
                      <template x-if="x.type !== 'login'"><span>@include('passwords._icon', ['expr' => 'x.type', 'cls' => 'h-4 w-4'])</span></template>
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="x.title"></span>
                      <span class="block truncate text-xs text-gray-400" x-text="x.type === 'card' ? (cardBrand(x.fields.number) || typeLabel('card')) : (x.fields.username || (x.fields.urls && x.fields.urls[0]) || x.fields.ssid || x.fields.host || x.fields.product || typeLabel(x.type))"></span>
                      <span x-show="view === 'health'" x-cloak class="mt-1 flex flex-wrap gap-1">
                        <template x-if="issuesFor(x) && issuesFor(x).breach > 0"><span class="rounded bg-red-100 dark:bg-red-900/40 px-1.5 py-0.5 text-[10px] font-medium text-red-700 dark:text-red-300">{{ __('passwords.issue_breached') }}</span></template>
                        <template x-if="issuesFor(x) && issuesFor(x).reused"><span class="rounded bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300">{{ __('passwords.issue_reused') }}</span></template>
                        <template x-if="issuesFor(x) && issuesFor(x).weak"><span class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-400">{{ __('passwords.issue_weak') }}</span></template>
                      </span>
                    </span>
                    <span x-show="x.favorite" class="shrink-0 text-amber-500"><x-icon name="star-solid" class="h-3.5 w-3.5" /></span>
                  </button>
                </template>
              </div>
            </div>
          </div>

          {{-- Right: detail or edit --}}
          <section class="min-w-0 flex-1">
            {{-- EDIT / NEW --}}
            <template x-if="draft">
              <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                <div class="mb-4 flex items-center gap-3">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg"
                        :class="(draft.type === 'login' && ! draft.icon) ? 'text-sm font-semibold text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500'"
                        :style="(draft.type === 'login' && ! draft.icon) ? ('background:' + avatarColor(draft)) : ''">
                    <template x-if="draft.type === 'login' && draft.icon"><img :src="draft.icon" alt="" class="h-full w-full object-contain"></template>
                    <template x-if="draft.type === 'login' && ! draft.icon"><span x-text="avatarText(draft)"></span></template>
                    <template x-if="draft.type !== 'login'"><span>@include('passwords._icon', ['expr' => 'draft.type', 'cls' => 'h-5 w-5'])</span></template>
                  </span>
                  <input type="text" x-model="draft.title" placeholder="{{ __('passwords.title_placeholder') }}" class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-base font-medium focus:border-gray-500 focus:ring-gray-500">
                </div>
                <div class="space-y-3">
                  <template x-for="[k, ft] in fieldsOf(draft.type)" :key="k">
                    <div>
                      <label class="block text-xs font-medium text-gray-500 dark:text-gray-400" x-text="fieldLabel(k)"></label>
                      {{-- text --}}
                      <template x-if="ft === 'text'"><input type="text" x-model="draft.fields[k]" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500"></template>
                      {{-- textarea --}}
                      <template x-if="ft === 'textarea'"><textarea x-model="draft.fields[k]" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500"></textarea></template>
                      {{-- select (security) --}}
                      <template x-if="ft === 'select'"><select x-model="draft.fields[k]" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500"><template x-for="o in securityOptions" :key="o"><option :value="o" x-text="o"></option></template></select></template>
                      {{-- checkbox (hidden) --}}
                      <template x-if="ft === 'checkbox'"><label class="mt-1 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" x-model="draft.fields[k]" class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0"> <span x-text="fieldLabel(k)"></span></label></template>
                      {{-- password/secret --}}
                      <template x-if="ft === 'password'">
                        <div class="mt-1 flex items-center gap-1.5">
                          <input :type="reveal[k] ? 'text' : 'password'" x-model="draft.fields[k]" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 font-mono text-sm focus:border-gray-500 focus:ring-gray-500">
                          <button type="button" @click="toggleReveal(k)" class="shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon x-show="!reveal[k]" name="eye" class="h-4 w-4" /><x-icon x-show="reveal[k]" name="eye-slash" class="h-4 w-4" /></button>
                          <button type="button" x-show="k === 'password'" @click="openGen(k)" title="{{ __('passwords.generate') }}" class="shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="arrow-path" class="h-4 w-4" /></button>
                        </div>
                      </template>
                      {{-- multi-url (login) --}}
                      <template x-if="ft === 'urls'">
                        <div class="mt-1 space-y-1.5">
                          <template x-for="(u, i) in draft.fields.urls" :key="i">
                            <div class="flex items-center gap-1.5">
                              <input type="text" x-model="draft.fields.urls[i]" placeholder="https://…" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500">
                              <button type="button" @click="removeUrl(i)" class="shrink-0 rounded-md p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-4 w-4" /></button>
                            </div>
                          </template>
                          <button type="button" @click="addUrl()" class="text-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">+ {{ __('passwords.add_url') }}</button>
                        </div>
                      </template>
                      {{-- live card-brand hint --}}
                      <p x-show="draft.type === 'card' && k === 'number' && cardBrand(draft.fields.number)" x-cloak class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="cardBrand(draft.fields.number)"></p>
                    </div>
                  </template>

                  {{-- Folder --}}
                  <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('passwords.folder') }}</label>
                    <select x-model="draft.folder" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500">
                      <option :value="null">{{ __('passwords.no_folder') }}</option>
                      <template x-for="f in folders" :key="f.id"><option :value="f.id" x-text="f.name"></option></template>
                    </select>
                  </div>
                  {{-- Tags --}}
                  <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('passwords.tags') }}</label>
                    <input type="text" x-model="tagsValue" placeholder="{{ __('passwords.tags_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500">
                  </div>
                  {{-- Custom fields --}}
                  <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('passwords.custom_fields') }}</label>
                    <div class="mt-1 space-y-2">
                      <template x-for="(c, i) in draft.custom" :key="i">
                        <div class="space-y-1.5 rounded-md border border-gray-200 dark:border-gray-800 p-2">
                          <div class="flex items-center gap-1.5">
                            <input type="text" x-model="c.label" placeholder="{{ __('passwords.custom_label') }}" class="min-w-0 flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500">
                            <select x-model="c.kind" class="shrink-0 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs focus:border-gray-500 focus:ring-gray-500">
                              <option value="text">{{ __('passwords.kind_text') }}</option>
                              <option value="secret">{{ __('passwords.kind_secret') }}</option>
                              <option value="multiline">{{ __('passwords.kind_multiline') }}</option>
                              <option value="url">{{ __('passwords.kind_url') }}</option>
                            </select>
                            <button type="button" @click="removeCustom(i)" class="shrink-0 rounded-md p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-4 w-4" /></button>
                          </div>
                          <template x-if="c.kind === 'multiline'"><textarea x-model="c.value" rows="2" placeholder="{{ __('passwords.custom_value') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500"></textarea></template>
                          <template x-if="c.kind !== 'multiline'">
                            <div class="flex items-center gap-1.5">
                              <input :type="c.kind === 'secret' && ! reveal['c' + i] ? 'password' : 'text'" x-model="c.value" placeholder="{{ __('passwords.custom_value') }}" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500" :class="c.kind === 'secret' ? 'font-mono' : ''">
                              <button type="button" x-show="c.kind === 'secret'" @click="toggleReveal('c' + i)" class="shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon x-show="!reveal['c' + i]" name="eye" class="h-4 w-4" /><x-icon x-show="reveal['c' + i]" name="eye-slash" class="h-4 w-4" /></button>
                            </div>
                          </template>
                        </div>
                      </template>
                      <button type="button" @click="addCustom()" class="text-xs font-medium text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">+ {{ __('passwords.add_field') }}</button>
                    </div>
                  </div>
                  <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" x-model="draft.favorite" class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">{{ __('passwords.favorite') }}</label>
                </div>

                {{-- Version history with what changed (edit of an existing item) --}}
                <div x-show="draft.id && current && versionChanges(current).length" x-cloak class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-3">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('passwords.versions_heading') }}</p>
                  <ul class="max-h-40 space-y-1.5 overflow-y-auto">
                    <template x-for="(vc, i) in (current ? versionChanges(current) : [])" :key="i">
                      <li class="flex items-center justify-between gap-3 text-xs">
                        <span class="min-w-0 truncate text-gray-600 dark:text-gray-300" x-text="vc.changed.length ? vc.changed.join(', ') : @js(__('passwords.no_change'))"></span>
                        <span class="shrink-0 tabular-nums text-gray-400" x-text="fmtDate(vc.at)"></span>
                      </li>
                    </template>
                  </ul>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                  <button type="button" @click="cancelEdit()" class="rounded-md px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('passwords.cancel') }}</button>
                  <button type="button" @click="save()" class="rounded-md bg-gray-900 dark:bg-gray-100 px-4 py-2 text-sm font-medium text-white dark:text-gray-900">{{ __('passwords.save') }}</button>
                </div>
              </div>
            </template>

            {{-- DETAIL (read-only) --}}
            <template x-if="! draft && current">
              <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                <div class="mb-4 flex items-start gap-3">
                  <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg"
                        :class="(current.type === 'login' && ! current.icon) ? 'text-sm font-semibold text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500'"
                        :style="(current.type === 'login' && ! current.icon) ? ('background:' + avatarColor(current)) : ''">
                    <template x-if="current.type === 'login' && current.icon"><img :src="current.icon" alt="" class="h-full w-full object-contain"></template>
                    <template x-if="current.type === 'login' && ! current.icon"><span x-text="avatarText(current)"></span></template>
                    <template x-if="current.type !== 'login'"><span>@include('passwords._icon', ['expr' => 'current.type', 'cls' => 'h-5 w-5'])</span></template>
                  </span>
                  <div class="min-w-0 flex-1">
                    <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="current.title"></h2>
                    <p class="text-xs text-gray-400" x-text="typeLabel(current.type) + (current.type === 'card' && cardBrand(current.fields.number) ? ' · ' + cardBrand(current.fields.number) : '') + ' · ' + @js(__('passwords.updated')) + ' ' + fmtDate(current.updated || current.created)"></p>
                    <div x-show="(current.tags || []).length" x-cloak class="mt-1 flex flex-wrap gap-1">
                      <template x-for="t in (current.tags || [])" :key="t"><span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] text-gray-600 dark:text-gray-400" x-text="'#' + t"></span></template>
                    </div>
                  </div>
                  <div class="flex shrink-0 items-center gap-1">
                    <button type="button" @click="toggleFavorite(current)" :class="current.favorite ? 'text-amber-500' : 'text-gray-400'" class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon x-show="current.favorite" name="star-solid" class="h-4 w-4" /><x-icon x-show="!current.favorite" name="star" class="h-4 w-4" /></button>
                    <button type="button" @click="openHistory(current)" :title="'{{ __('passwords.history') }}'" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="clock" class="h-4 w-4" /><span x-show="(current.versions||[]).length" x-text="(current.versions||[]).length" class="ml-0.5 text-[10px]"></span></button>
                    <button type="button" @click="editCurrent()" title="{{ __('passwords.edit') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="pencil" class="h-4 w-4" /></button>
                    <button type="button" x-show="view !== 'trash'" @click="trash(current)" title="{{ __('passwords.delete') }}" class="rounded-lg p-2 text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"><x-icon name="trash" class="h-4 w-4" /></button>
                    <button type="button" x-show="view === 'trash'" @click="restore(current)" title="{{ __('passwords.restore') }}" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="arrow-uturn-left" class="h-4 w-4" /></button>
                  </div>
                </div>

                {{-- Password-health warning --}}
                <div x-show="hasIssue(current)" x-cloak class="mb-3 flex items-start gap-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 px-4 py-2.5 text-sm text-amber-800 dark:text-amber-300">
                  <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                  <div class="min-w-0 flex-1 space-y-0.5">
                    <p x-show="issuesFor(current) && issuesFor(current).breach > 0" x-text="@js(__('passwords.breach_warn', ['count' => '{n}'])).replace('{n}', String((issuesFor(current) && issuesFor(current).breach) || 0))"></p>
                    <div x-show="issuesFor(current) && issuesFor(current).reused">
                      <p>{{ __('passwords.reused_warn') }}</p>
                      <div class="mt-1.5 flex flex-wrap gap-1">
                        <span class="text-xs text-amber-700/80 dark:text-amber-300/80">{{ __('passwords.reused_where') }}</span>
                        <template x-for="y in reusedWith(current)" :key="y.id">
                          <button type="button" @click="openItem(y)" class="rounded bg-amber-100 dark:bg-amber-900/40 px-1.5 py-0.5 text-xs font-medium hover:underline" x-text="y.title"></button>
                        </template>
                      </div>
                    </div>
                    <p x-show="issuesFor(current) && issuesFor(current).weak">{{ __('passwords.weak_warn') }}</p>
                    <button type="button" x-show="issuesFor(current) && issuesFor(current).breach === null" @click="checkBreaches()" :disabled="breachChecking" class="text-xs font-medium underline disabled:opacity-50" x-text="breachChecking ? '{{ __('passwords.checking') }}' : '{{ __('passwords.check_breaches') }}'"></button>
                  </div>
                </div>

                {{-- TOTP (login) --}}
                <div x-show="hasTotp(current)" x-cloak class="mb-3 flex items-center justify-between gap-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 px-4 py-3">
                  <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ __('passwords.f_totp') }}</p>
                    <p class="font-mono text-2xl font-semibold tabular-nums text-gray-900 dark:text-gray-100" x-text="totpCode(current)"></p>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="text-sm tabular-nums text-gray-400" x-text="totpRemain(current) + 's'"></span>
                    <button type="button" @click="copy((totpNow[current.id]||{}).code)" class="rounded-md p-2 text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700"><x-icon name="clipboard" class="h-4 w-4" /></button>
                  </div>
                </div>

                {{-- Wi-Fi QR --}}
                <div x-show="current.type === 'wifi' && wifiQr" x-cloak class="mb-3 flex flex-col items-center rounded-lg border border-gray-100 dark:border-gray-800 p-4">
                  <img :src="wifiQr" alt="" class="h-44 w-44 rounded bg-white p-1">
                  <p class="mt-2 text-xs text-gray-400">{{ __('passwords.wifi_scan') }}</p>
                </div>

                {{-- Fields --}}
                <dl class="space-y-3">
                  <template x-for="[k, ft] in fieldsOf(current.type)" :key="'d' + k">
                    <div x-show="k !== 'totp' && (ft === 'checkbox' ? true : (ft === 'urls' ? (current.fields[k] || []).some((u) => u) : (current.fields[k] !== '' && current.fields[k] != null)))">
                      <dt class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="fieldLabel(k)"></dt>
                      <dd class="mt-0.5 flex items-start gap-2">
                        <template x-if="ft === 'checkbox'"><span class="text-sm text-gray-900 dark:text-gray-100" x-text="current.fields[k] ? @js(__('passwords.yes')) : @js(__('passwords.no'))"></span></template>
                        {{-- multi-url --}}
                        <template x-if="ft === 'urls'">
                          <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                            <template x-for="(u, i) in (current.fields[k] || [])" :key="i">
                              <span class="flex min-w-0 items-center gap-2">
                                <a :href="/^https?:\/\//.test(u) ? u : 'https://' + u" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 break-all text-sm text-gray-900 dark:text-gray-100 underline hover:text-gray-600" x-text="u"></a>
                                <button type="button" @click="copy(u)" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="clipboard" class="h-4 w-4" /></button>
                              </span>
                            </template>
                          </div>
                        </template>
                        <template x-if="ft !== 'checkbox' && ft !== 'urls' && isSecret(k)">
                          <span class="flex min-w-0 flex-1 items-center gap-2">
                            <span role="button" tabindex="0" @click="toggleReveal(k)" @keydown.enter="toggleReveal(k)" :title="reveal[k] ? '{{ __('passwords.hide') }}' : '{{ __('passwords.reveal') }}'" class="min-w-0 flex-1 cursor-pointer break-all font-mono text-sm text-gray-900 dark:text-gray-100 select-all" x-text="reveal[k] ? current.fields[k] : '••••••••••'"></span>
                            <button type="button" @click="toggleReveal(k)" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon x-show="!reveal[k]" name="eye" class="h-4 w-4" /><x-icon x-show="reveal[k]" name="eye-slash" class="h-4 w-4" /></button>
                            <button type="button" @click="showBig(current.fields[k])" title="{{ __('passwords.large_type') }}" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="arrows-pointing-out" class="h-4 w-4" /></button>
                            <button type="button" @click="copy(current.fields[k])" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="clipboard" class="h-4 w-4" /></button>
                          </span>
                        </template>
                        <template x-if="ft !== 'checkbox' && ft !== 'urls' && ! isSecret(k)">
                          <span class="flex min-w-0 flex-1 items-center gap-2">
                            <span class="min-w-0 flex-1 break-all text-sm text-gray-900 dark:text-gray-100" :class="ft === 'textarea' ? 'whitespace-pre-wrap' : ''" x-text="current.fields[k]"></span>
                            <button type="button" @click="copy(current.fields[k])" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="clipboard" class="h-4 w-4" /></button>
                          </span>
                        </template>
                      </dd>
                    </div>
                  </template>
                  {{-- Custom fields --}}
                  <template x-for="(c, i) in (current.custom || [])" :key="'c' + i">
                    <div x-show="c.value">
                      <dt class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="c.label"></dt>
                      <dd class="mt-0.5 flex items-start gap-2">
                        <template x-if="customKind(c) === 'url'"><a :href="/^https?:\/\//.test(c.value) ? c.value : 'https://' + c.value" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 break-all text-sm text-gray-900 dark:text-gray-100 underline hover:text-gray-600" x-text="c.value"></a></template>
                        <template x-if="customKind(c) !== 'url'"><span :role="customKind(c) === 'secret' ? 'button' : null" @click="customKind(c) === 'secret' && toggleReveal('c' + i)" class="min-w-0 flex-1 break-all text-sm text-gray-900 dark:text-gray-100" :class="(customKind(c) === 'secret' ? 'font-mono cursor-pointer ' : '') + (customKind(c) === 'multiline' ? 'whitespace-pre-wrap' : '')" x-text="(customKind(c) === 'secret' && ! reveal['c' + i]) ? '••••••••••' : c.value"></span></template>
                        <button type="button" x-show="customKind(c) === 'secret'" @click="toggleReveal('c' + i)" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon x-show="!reveal['c' + i]" name="eye" class="h-4 w-4" /><x-icon x-show="reveal['c' + i]" name="eye-slash" class="h-4 w-4" /></button>
                        <button type="button" x-show="customKind(c) === 'secret'" @click="showBig(c.value)" title="{{ __('passwords.large_type') }}" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="arrows-pointing-out" class="h-4 w-4" /></button>
                        <button type="button" @click="copy(c.value)" class="shrink-0 rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="clipboard" class="h-4 w-4" /></button>
                      </dd>
                    </div>
                  </template>
                </dl>
              </div>
            </template>

            {{-- Empty --}}
            <template x-if="! draft && ! current">
              <div class="flex h-full min-h-[40vh] flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-12 text-center">
                <x-icon name="key" class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('passwords.pick_hint') }}</p>
              </div>
            </template>
          </section>
        </div>

        {{-- Version history modal --}}
        <div x-show="history.open" x-cloak class="fixed inset-0 z-[960] flex items-center justify-center p-4" @keydown.escape.window="closeHistory()">
          <div class="absolute inset-0 bg-gray-900/50" @click="closeHistory()"></div>
          <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl">
            <div class="flex items-center justify-between"><h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('passwords.history') }}</h3><button type="button" @click="closeHistory()" class="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-5 w-5" /></button></div>
            <template x-if="! (history.item?.versions || []).length"><p class="mt-6 text-center text-sm text-gray-400">{{ __('passwords.history_empty') }}</p></template>
            <ul class="mt-3 max-h-[60vh] space-y-2 overflow-y-auto">
              <template x-for="(v, i) in (history.item?.versions || [])" :key="i">
                <li class="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="fmtDate(v.at)"></span>
                    <button type="button" @click="restoreVersion(v)" class="rounded-md px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('passwords.version_restore') }}</button>
                  </div>
                  <p class="mt-1 truncate text-sm text-gray-800 dark:text-gray-200" x-text="v.title"></p>
                  <p class="mt-0.5 truncate text-xs text-gray-400" x-text="Object.entries(v.fields || {}).filter(([kk, vv]) => vv && ! ['password','totp','cvv','pin','licensekey'].includes(kk)).map(([kk, vv]) => fieldLabel(kk) + ': ' + vv).join(' · ')"></p>
                </li>
              </template>
            </ul>
          </div>
        </div>

        {{-- Password generator modal --}}
        <div x-show="gen.open" x-cloak class="fixed inset-0 z-[961] flex items-center justify-center p-4" @keydown.escape.window="gen.open = false">
          <div class="absolute inset-0 bg-gray-900/50" @click="gen.open = false"></div>
          <div class="relative w-full max-w-sm rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('passwords.generate') }}</h3>
            <label class="mt-4 block text-xs text-gray-500 dark:text-gray-400">{{ __('passwords.length') }}: <span x-text="gen.length"></span>
              <input type="range" min="8" max="64" x-model.number="gen.length" class="mt-1 w-full">
            </label>
            <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-gray-700 dark:text-gray-300">
              <label class="flex items-center gap-2"><input type="checkbox" x-model="gen.upper" class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">A-Z</label>
              <label class="flex items-center gap-2"><input type="checkbox" x-model="gen.lower" class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">a-z</label>
              <label class="flex items-center gap-2"><input type="checkbox" x-model="gen.digits" class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">0-9</label>
              <label class="flex items-center gap-2"><input type="checkbox" x-model="gen.symbols" class="rounded border-gray-300 dark:border-gray-600 text-gray-900 focus:ring-0">!@#</label>
            </div>
            <div class="mt-5 flex justify-end gap-2">
              <button type="button" @click="gen.open = false" class="rounded-md px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('passwords.cancel') }}</button>
              <button type="button" @click="applyGen()" class="rounded-md bg-gray-900 dark:bg-gray-100 px-4 py-2 text-sm font-medium text-white dark:text-gray-900">{{ __('passwords.generate_apply') }}</button>
            </div>
          </div>
        </div>

        {{-- Import modal --}}
        <div x-show="importOpen" x-cloak class="fixed inset-0 z-[961] flex items-center justify-center p-4" @keydown.escape.window="importOpen = false">
          <div class="absolute inset-0 bg-gray-900/50" @click="importOpen = false"></div>
          <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl">
            <div class="flex items-center justify-between"><h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('passwords.import') }}</h3><button type="button" @click="importOpen = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><x-icon name="x-mark" class="h-5 w-5" /></button></div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('passwords.import_intro') }}</p>
            <label class="mt-4 block text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('passwords.import_format') }}
              <select x-model="importFormat" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:border-gray-500 focus:ring-gray-500">
                <option value="auto">{{ __('passwords.fmt_auto') }}</option>
                <option value="bitwarden_json">{{ __('passwords.fmt_bitwarden_json') }}</option>
                <option value="bitwarden_csv">{{ __('passwords.fmt_bitwarden_csv') }}</option>
                <option value="onepassword">{{ __('passwords.fmt_onepassword') }}</option>
                <option value="lastpass">{{ __('passwords.fmt_lastpass') }}</option>
                <option value="keepass">{{ __('passwords.fmt_keepass') }}</option>
                <option value="generic">{{ __('passwords.fmt_generic') }}</option>
              </select>
            </label>
            <div class="mt-4">
              <input type="file" accept=".json,.csv,text/csv,application/json" @change="importFile($event)" :disabled="importing" class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white dark:file:bg-gray-100 dark:file:text-gray-900">
            </div>
            <p x-show="importing" x-cloak class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('passwords.import_running') }}</p>
            <p x-show="importResult && importResult.ok" x-cloak class="mt-3 text-sm text-green-600 dark:text-green-400" x-text="@js(__('passwords.import_done', ['count' => '{n}'])).replace('{n}', importResult ? importResult.count : 0)"></p>
            <p x-show="importResult && ! importResult.ok" x-cloak class="mt-3 text-sm text-red-600 dark:text-red-400">{{ __('passwords.import_failed') }}</p>
            <p class="mt-3 text-[11px] leading-relaxed text-gray-400">{{ __('passwords.import_hint') }}</p>
            <div class="mt-4 flex justify-end">
              <button type="button" @click="importOpen = false" class="rounded-md px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">{{ __('passwords.close') }}</button>
            </div>
          </div>
        </div>

        {{-- Large-type view (readable, colour-coded, mono) --}}
        <div x-show="bigType.open" x-cloak class="fixed inset-0 z-[970] flex items-center justify-center p-6" @keydown.escape.window="closeBig()" @click="closeBig()">
          <div class="absolute inset-0 bg-white/95 dark:bg-gray-950/95"></div>
          <div class="relative w-full max-w-3xl text-center" @click.stop>
            <p class="mb-6 text-xs uppercase tracking-wide text-gray-400">{{ __('passwords.large_type') }}</p>
            <p class="break-all font-mono text-4xl leading-relaxed tracking-widest sm:text-5xl">
              <template x-for="(ch, i) in bigChars()" :key="i"><span :class="ch.cls" x-text="ch.c === ' ' ? ' ' : ch.c"></span></template>
            </p>
            <button type="button" @click="closeBig()" class="mt-8 rounded-md bg-gray-900 dark:bg-gray-100 px-5 py-2 text-sm font-medium text-white dark:text-gray-900">{{ __('passwords.close') }}</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</x-layouts.app>
