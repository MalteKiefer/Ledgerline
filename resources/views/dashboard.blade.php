<x-layouts.app :title="__('messages.nav.dashboard')">
<div x-data="dashboard({ rawBase: '{{ url('/gallery/raw') }}' }, {})">

    {{-- Zero-knowledge gate --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
        <div class="mx-auto mt-16 max-w-md ll-card !p-8 text-center">
            <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
               x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
            <button type="button" @click="$dispatch('vault-panel')"
                class="mt-5 inline-flex min-h-11 items-center gap-1.5 ll-accent rounded-xl px-4 py-2 text-sm font-medium">
                <x-icon name="lock-open" class="h-4 w-4" />
                <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
            </button>
        </div>
    </template>

    <template x-if="state === 'ready'">
        <div>
            {{-- Header --}}
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('dashboard.greeting', ['name' => auth()->user()->name]) }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ now()->isoFormat('dddd, D. MMMM YYYY') }}
                </p>
            </div>

            {{-- Running-fast banner (always shown while a fast is active) --}}
            <template x-if="activeFast">
              <a href="/health"
                 class="mb-4 flex items-center gap-3 rounded-2xl border border-accent/30 bg-accent/5 px-4 py-3 transition hover:bg-accent/10">
                  <span class="ll-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#e2915a">
                      <x-icon name="clock" class="h-4 w-4 text-white" />
                  </span>
                  <span class="min-w-0 flex-1">
                      <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                          <span>{{ __('health.fasting') }}</span>
                          <span class="text-gray-400">·</span>
                          <span x-text="fastWindowLabel(activeFast.targetHours)"></span>
                          <span class="text-gray-400">·</span>
                          <span x-text="fastElapsedLabel(activeFast) + ' / ' + fastTargetLabel(activeFast)"></span>
                          <template x-if="activeFastProgress && activeFastProgress.reached">
                              <span class="rounded-full bg-green-500/15 px-2 py-0.5 text-xs font-medium text-green-600 dark:text-green-400">{{ __('health.fasting_reached') }}</span>
                          </template>
                      </span>
                      <span class="mt-1 block h-1.5 w-full overflow-hidden rounded-full bg-black/[0.06] dark:bg-white/10">
                          <span class="block h-full rounded-full transition-all"
                                :class="activeFastProgress && activeFastProgress.reached ? 'bg-green-500' : 'bg-accent'"
                                :style="'width:' + fastPct(activeFast) + '%'"></span>
                      </span>
                  </span>
                  <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-400" />
              </a>
            </template>

            {{-- Widget grid --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">

                {{-- ── Todos widget ── --}}
                <div class="ll-card flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.todos_title') }}
                        </h2>
                        <a href="{{ route('todos.index') }}"
                            class="text-xs font-medium text-accent hover:underline">
                            {{ __('dashboard.todos_open') }}
                        </a>
                    </div>

                    <template x-if="todos.length === 0">
                        <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white mb-2"
                                style="background:#7066f5">
                                <x-icon name="todos" class="h-5 w-5" />
                            </span>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                {{ __('dashboard.todos_empty') }}
                            </p>
                        </div>
                    </template>

                    <template x-if="todos.length > 0">
                        <ul class="divide-y divide-black/[0.06] dark:divide-white/10 -mx-4 px-0">
                            <template x-for="t in todos" :key="t.id">
                                <li class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors group">
                                    <input type="checkbox"
                                        :checked="t.done"
                                        @change="completeTodo(t.id)"
                                        class="h-4 w-4 shrink-0 rounded border-gray-300 dark:border-gray-600 cursor-pointer"
                                        style="accent-color:#7066f5" />
                                    <span class="flex-1 min-w-0 text-sm text-gray-800 dark:text-gray-200 truncate"
                                        x-text="t.title || ''"></span>
                                    <template x-if="t.due">
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="t.due < new Date().toISOString().slice(0,10)
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400'"
                                            x-text="t.due < new Date().toISOString().slice(0,10)
                                                ? @js(__('dashboard.due_overdue'))
                                                : t.due.slice(5).replace('-','/')"></span>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- ── Counter tiles ── --}}
                <div class="ll-card">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.counts_title') }}
                        </h2>
                    </div>
                    <ul class="!p-0 -mx-4 divide-y divide-black/[0.06] dark:divide-white/10">
                        <li>
                            <a href="{{ route('notes.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                <span class="ll-chip h-8 w-8 rounded-lg" style="--chip:#3fae9f">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ __('dashboard.notes_module') }}</span>
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400" x-text="counts.notes"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('passwords.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                <span class="ll-chip h-8 w-8 rounded-lg" style="--chip:#7066f5">
                                    <x-icon name="key" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ __('dashboard.passwords_module') }}</span>
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400" x-text="counts.passwords"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('contacts.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                <span class="ll-chip h-8 w-8 rounded-lg" style="--chip:#3b9fd6">
                                    <x-icon name="contacts" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ __('dashboard.contacts_module') }}</span>
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400" x-text="counts.contacts"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('bookmarks.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                <span class="ll-chip h-8 w-8 rounded-lg" style="--chip:#d9a441">
                                    <x-icon name="bookmark" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ __('dashboard.bookmarks_module') }}</span>
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400" x-text="counts.bookmarks"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('invoices.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                <span class="ll-chip h-8 w-8 rounded-lg" style="--chip:#e2915a">
                                    <x-icon name="document-text" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ __('dashboard.invoices_module') }}</span>
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400" x-text="counts.invoices"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('files.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                <span class="ll-chip h-8 w-8 rounded-lg" style="--chip:#6b7280">
                                    <x-icon name="files" class="h-4 w-4" />
                                </span>
                                <span class="flex-1 text-sm text-gray-800 dark:text-gray-200">{{ __('dashboard.files_module') }}</span>
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400" x-text="counts.files"></span>
                                <x-icon name="chevron-right" class="h-4 w-4 text-gray-400" />
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- ── Recent notes ── --}}
                <div class="ll-card flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.notes_title') }}
                        </h2>
                        <a href="{{ route('notes.index') }}"
                            class="text-xs font-medium text-accent hover:underline">
                            {{ __('dashboard.notes_open') }}
                        </a>
                    </div>

                    <template x-if="recentNotes.length === 0">
                        <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white mb-2"
                                style="background:#3fae9f">
                                <x-icon name="pencil" class="h-5 w-5" />
                            </span>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                {{ __('dashboard.notes_empty') }}
                            </p>
                        </div>
                    </template>

                    <template x-if="recentNotes.length > 0">
                        <ul class="divide-y divide-black/[0.06] dark:divide-white/10 -mx-4 px-0">
                            <template x-for="n in recentNotes" :key="n.id">
                                <li>
                                    <a :href="'{{ route('notes.index') }}?note=' + encodeURIComponent(n.id)"
                                        class="flex flex-col gap-0.5 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate"
                                            x-text="n.title || @js(__('notes.untitled'))"></span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500"
                                            x-text="n.updated ? n.updated.slice(0,10) : ''"></span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- ── Birthdays & anniversaries widget ── --}}
                <div class="ll-card flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.birthdays_title') }}
                        </h2>
                        <a href="{{ route('contacts.index') }}"
                            class="text-xs font-medium text-accent hover:underline">
                            {{ __('dashboard.open') }}
                        </a>
                    </div>

                    <template x-if="birthdays.length === 0">
                        <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white mb-2"
                                style="background:#d16ba5">
                                <x-icon name="cake" class="h-5 w-5" />
                            </span>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                {{ __('dashboard.birthdays_empty') }}
                            </p>
                        </div>
                    </template>

                    <template x-if="birthdays.length > 0">
                        <ul class="divide-y divide-black/[0.06] dark:divide-white/10 -mx-4 px-0">
                            <template x-for="b in birthdays" :key="b.id + b.kind">
                                <li>
                                    <a href="{{ route('contacts.index') }}"
                                        class="flex items-center gap-3 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                        <span class="ll-chip h-8 w-8 shrink-0 rounded-lg"
                                            :style="'--chip:' + (b.kind === 'anniversary' ? '#9e70fa' : '#d16ba5')">
                                            <template x-if="b.kind === 'birthday'">
                                                <x-icon name="cake" class="h-4 w-4" />
                                            </template>
                                            <template x-if="b.kind === 'anniversary'">
                                                <x-icon name="heart" class="h-4 w-4" />
                                            </template>
                                        </span>
                                        <span class="flex-1 min-w-0 text-sm text-gray-800 dark:text-gray-200 truncate"
                                            x-text="b.name"></span>
                                        <span class="shrink-0 text-right">
                                            <span class="block text-xs text-gray-500 dark:text-gray-400"
                                                x-text="b.in === 0
                                                    ? @js(__('dashboard.today'))
                                                    : @js(__('dashboard.in_days')).replace(':n', b.in)"></span>
                                            <template x-if="b.turning !== null && b.kind === 'birthday'">
                                                <span class="block text-xs text-accent"
                                                    x-text="@js(__('dashboard.turning')).replace(':n', b.turning)"></span>
                                            </template>
                                        </span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- ── Health widget ── --}}
                <div class="ll-card flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.health_title') }}
                        </h2>
                        <a href="{{ route('health.index') }}"
                            class="text-xs font-medium text-accent hover:underline">
                            {{ __('dashboard.open') }}
                        </a>
                    </div>

                    {{-- Latest values chips --}}
                    <template x-if="healthLatest.length === 0">
                        <div class="flex flex-col items-center justify-center py-4 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white mb-2"
                                style="background:#0ea5e9">
                                <x-icon name="heart" class="h-5 w-5" />
                            </span>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                {{ __('dashboard.health_empty') }}
                            </p>
                        </div>
                    </template>

                    <template x-if="healthLatest.length > 0">
                        <div class="flex flex-wrap gap-2 mb-3">
                            <template x-for="h in healthLatest" :key="h.key">
                                <span class="ll-chip w-auto h-auto px-2.5 py-1 rounded-lg text-xs font-medium"
                                    :style="'--chip:' + _tintHex(h.tint)">
                                    <span x-text="h.display"></span>
                                </span>
                            </template>
                        </div>
                    </template>

                    {{-- Weight sparkline --}}
                    <div x-ref="spark" class="h-12 w-full mb-3 overflow-hidden"></div>

                    {{-- Quick add --}}
                    <div class="border-t border-black/[0.06] dark:border-white/10 pt-3">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
                            {{ __('dashboard.quick_add') }}
                        </p>
                        <div class="flex flex-wrap items-end gap-2">
                            <select x-model="quickAdd.metric"
                                class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-2 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-accent">
                                <option value="weight">{{ __('health.metric_weight') }}</option>
                                <option value="bp">{{ __('health.metric_bp') }}</option>
                                <option value="pulse">{{ __('health.metric_pulse') }}</option>
                                <option value="spo2">{{ __('health.metric_spo2') }}</option>
                                <option value="temp">{{ __('health.metric_temp') }}</option>
                                <option value="glucose">{{ __('health.metric_glucose') }}</option>
                            </select>
                            <input type="number" x-model="quickAdd.v"
                                :placeholder="_unitLabel(quickAdd.metric)"
                                @keydown.enter="saveQuickAdd()"
                                class="w-20 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-2 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-accent" />
                            <template x-if="quickAdd.metric === 'bp'">
                                <input type="number" x-model="quickAdd.v2"
                                    placeholder="dia"
                                    @keydown.enter="saveQuickAdd()"
                                    class="w-16 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-2 py-1.5 text-xs text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-accent" />
                            </template>
                            <button type="button" @click="saveQuickAdd()"
                                class="ll-accent rounded-lg px-3 py-1.5 text-xs font-medium text-white">
                                {{ __('dashboard.add') }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── On This Day widget ── --}}
                <template x-if="galleryReady">
                    <div class="ll-card flex flex-col">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ __('dashboard.on_this_day_title') }}
                            </h2>
                            <a href="{{ route('gallery.index') }}"
                                class="text-xs font-medium text-accent hover:underline">
                                {{ __('dashboard.open') }}
                            </a>
                        </div>

                        <template x-if="onThisDay.length === 0">
                            <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white mb-2"
                                    style="background:#9e70fa">
                                    <x-icon name="photo" class="h-5 w-5" />
                                </span>
                                <p class="text-sm text-gray-400 dark:text-gray-500">
                                    {{ __('dashboard.on_this_day_empty') }}
                                </p>
                            </div>
                        </template>

                        <template x-if="onThisDay.length > 0">
                            <div class="space-y-3">
                                <template x-for="group in onThisDay" :key="group.yearsAgo">
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2"
                                            x-text="@js(__('dashboard.on_this_day_years_ago')).replace(':count', group.yearsAgo)"></p>
                                        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
                                            <template x-for="photo in group.photos.slice(0, 12)" :key="photo.id">
                                                <a :href="'{{ route('gallery.index') }}?photo=' + encodeURIComponent(photo.id)"
                                                    class="shrink-0 block rounded-xl overflow-hidden bg-gray-100 dark:bg-white/5"
                                                    style="width:72px;height:72px">
                                                    <img
                                                        x-init="thumbUrl(photo).then(u => { if (u) $el.src = u; })"
                                                        :alt="''"
                                                        class="h-full w-full object-cover"
                                                        style="display:block" />
                                                </a>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ── Storage widget ── --}}
                <div class="ll-card flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.storage_title') }}
                        </h2>
                    </div>

                    <div class="space-y-4">
                        {{-- Files storage --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ __('dashboard.storage_files') }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <template x-if="usage.files === null">
                                        <span>—</span>
                                    </template>
                                    <template x-if="usage.files !== null && usage.files.quota > 0">
                                        <span x-text="@js(__('dashboard.storage_of'))
                                            .replace(':used', _fmtBytes(usage.files.used))
                                            .replace(':quota', _fmtBytes(usage.files.quota))"></span>
                                    </template>
                                    <template x-if="usage.files !== null && !(usage.files.quota > 0)">
                                        <span x-text="@js(__('dashboard.storage_used'))
                                            .replace(':used', _fmtBytes(usage.files.used))"></span>
                                    </template>
                                </span>
                            </div>
                            <template x-if="usage.files !== null && usage.files.quota > 0">
                                <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                                    <div class="h-full rounded-full ll-accent transition-all"
                                        :style="'width:' + Math.min(100, Math.round(usage.files.used / usage.files.quota * 100)) + '%'"></div>
                                </div>
                            </template>
                        </div>

                        {{-- Gallery storage --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ __('dashboard.storage_gallery') }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    <template x-if="usage.gallery === null">
                                        <span>—</span>
                                    </template>
                                    <template x-if="usage.gallery !== null && usage.gallery.quota > 0">
                                        <span x-text="@js(__('dashboard.storage_of'))
                                            .replace(':used', _fmtBytes(usage.gallery.used))
                                            .replace(':quota', _fmtBytes(usage.gallery.quota))"></span>
                                    </template>
                                    <template x-if="usage.gallery !== null && !(usage.gallery.quota > 0)">
                                        <span x-text="@js(__('dashboard.storage_used'))
                                            .replace(':used', _fmtBytes(usage.gallery.used))"></span>
                                    </template>
                                </span>
                            </div>
                            <template x-if="usage.gallery !== null && usage.gallery.quota > 0">
                                <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
                                    <div class="h-full rounded-full ll-accent transition-all"
                                        :style="'width:' + Math.min(100, Math.round(usage.gallery.used / usage.gallery.quota * 100)) + '%'"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- ── Password Health widget ── --}}
                <div class="ll-card flex flex-col">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('dashboard.pw_health_title') }}
                        </h2>
                        <a href="{{ route('passwords.index') }}"
                            class="text-xs font-medium text-accent hover:underline">
                            {{ __('dashboard.open') }}
                        </a>
                    </div>

                    <template x-if="pwHealth.reused === 0 && pwHealth.cards === 0 && pwHealth.no2fa === 0">
                        <div class="flex flex-1 flex-col items-center justify-center py-6 text-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white mb-2"
                                style="background:#59ad6b">
                                <x-icon name="shield-check" class="h-5 w-5" />
                            </span>
                            <p class="text-sm text-gray-400 dark:text-gray-500">
                                {{ __('dashboard.pw_health_all_good') }}
                            </p>
                        </div>
                    </template>

                    <template x-if="pwHealth.reused > 0 || pwHealth.cards > 0 || pwHealth.no2fa > 0">
                        <ul class="!p-0 -mx-4 divide-y divide-black/[0.06] dark:divide-white/10">
                            <template x-if="pwHealth.reused > 0">
                                <li class="flex items-center gap-3 px-4 py-2.5">
                                    <span class="ll-chip h-8 w-8 shrink-0 rounded-lg" style="--chip:#e2915a">
                                        <x-icon name="arrow-path" class="h-4 w-4" />
                                    </span>
                                    <span class="flex-1 text-sm text-gray-800 dark:text-gray-200"
                                        x-text="@js(__('dashboard.pw_health_reused')).replace(':n', pwHealth.reused)"></span>
                                </li>
                            </template>
                            <template x-if="pwHealth.cards > 0">
                                <li class="flex items-center gap-3 px-4 py-2.5">
                                    <span class="ll-chip h-8 w-8 shrink-0 rounded-lg" style="--chip:#3b9fd6">
                                        <x-icon name="credit-card" class="h-4 w-4" />
                                    </span>
                                    <span class="flex-1 text-sm text-gray-800 dark:text-gray-200"
                                        x-text="@js(__('dashboard.pw_health_expiring_cards')).replace(':n', pwHealth.cards)"></span>
                                </li>
                            </template>
                            <template x-if="pwHealth.no2fa > 0">
                                <li class="flex items-center gap-3 px-4 py-2.5">
                                    <span class="ll-chip h-8 w-8 shrink-0 rounded-lg" style="--chip:#d9a441">
                                        <x-icon name="shield-exclamation" class="h-4 w-4" />
                                    </span>
                                    <span class="flex-1 text-sm text-gray-800 dark:text-gray-200"
                                        x-text="@js(__('dashboard.pw_health_no_twofa')).replace(':n', pwHealth.no2fa)"></span>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

            </div>
        </div>
    </template>

</div>
</x-layouts.app>
