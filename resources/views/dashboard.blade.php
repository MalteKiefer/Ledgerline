<x-layouts.app :title="__('messages.nav.dashboard')">
<div x-data="dashboard({}, {})">

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
                                    <a href="{{ route('notes.index') }}"
                                        class="flex flex-col gap-0.5 px-4 py-2.5 hover:bg-accent/5 transition-colors">
                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate"
                                            x-text="n.title || @js(__('dashboard.notes_empty'))"></span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500"
                                            x-text="n.updated ? n.updated.slice(0,10) : ''"></span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- ── Placeholder slots for Task-4 / Task-5 widgets ── --}}
                {{-- Task 4: birthdays & anniversaries widget --}}
                {{-- Task 5: health / on-this-day / gallery widget --}}

            </div>
        </div>
    </template>

</div>
</x-layouts.app>
