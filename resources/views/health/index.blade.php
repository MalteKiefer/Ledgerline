<x-layouts.app :title="__('health.title')">
  {{-- Print styles for the report view --}}
  <style>
    @media print {
      /* Hide chrome — nav, sidebar, action buttons */
      nav, .x-nav, header, footer, aside,
      [x-ref="mobileNav"], [x-ref="nav"],
      .no-print { display: none !important; }

      /* White background, A4 margins */
      body, html { background: #fff !important; color: #000 !important; }
      .health-report { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
      .ll-card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
      }

      /* Keep each metric block together */
      .break-inside-avoid { break-inside: avoid; page-break-inside: avoid; }

      /* Charts fill full width */
      .report-chart-wrap, .report-chart-wrap canvas, .u-wrap { width: 100% !important; }

      /* Make text readable */
      .text-gray-400, .text-gray-500 { color: #555 !important; }
      .text-gray-600, .text-gray-700 { color: #333 !important; }
      .text-gray-900, .text-gray-100 { color: #000 !important; }

      /* Colour dots for classification — keep */
      .bg-green-500 { background-color: #22c55e !important; }
      .bg-amber-400 { background-color: #fbbf24 !important; }
      .bg-red-500   { background-color: #ef4444 !important; }
    }
  </style>

  <div x-data="health({
        deleteConfirm: @js(__('health.delete_confirm')),
        userName: @js(auth()->user()->name ?? ''),
        metricLabels: {
            weight: @js(__('health.metric_weight')), bp: @js(__('health.metric_bp')), pulse: @js(__('health.metric_pulse')),
            spo2: @js(__('health.metric_spo2')), temp: @js(__('health.metric_temp')), glucose: @js(__('health.metric_glucose')),
        },
        referenceNotes: {
            bp:    @js(__('health.report_reference_bp')),
            pulse: @js(__('health.report_reference_pulse')),
            spo2:  @js(__('health.report_reference_spo2')),
            temp:  @js(__('health.report_reference_temp')),
        },
     })">

    {{-- Zero-knowledge gate: health data decrypts with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <template x-if="state === 'locked'">
        <div class="mx-auto mt-16 max-w-md ll-card !p-8 text-center">
            <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400"
               x-text="$store.vault.configured ? @js(__('vault.unlock_hint')) : @js(__('vault.setup_hint'))"></p>
            <button type="button" @click="$dispatch('vault-panel')"
                class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md ll-accent px-4 py-2 text-sm font-medium hover:brightness-105">
                <x-icon name="lock-open" class="h-4 w-4" />
                <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
            </button>
        </div>
    </template>

    <template x-if="state === 'error'">
        <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('health.save_failed') }}</p>
    </template>

    <template x-if="state === 'ready'">
      <div>

      {{-- ===== MAIN VIEW ===== --}}
      <div x-show="view === 'main'" class="flex h-[calc(100dvh-11rem)] gap-4 md:h-[calc(100vh-10rem)]">

        {{-- Metric nav sidebar — iOS grouped list (icon chip + name + latest value). --}}
        <aside class="hidden md:flex w-64 shrink-0 flex-col ll-card !p-0 overflow-hidden">
            <div class="min-h-0 w-full flex-1 divide-y divide-black/[0.06] dark:divide-white/10 overflow-y-auto">
                <template x-for="m in metrics" :key="m.key">
                    <button type="button"
                        @click="selectedMetric = m.key"
                        class="flex w-full items-center gap-3 px-3.5 py-3 text-left transition-colors hover:bg-accent/5"
                        :class="selectedMetric === m.key ? 'bg-accent/10' : ''">
                        <span class="ll-chip relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                              :style="'background:' + tintHex(m.tint)">
                            @include('health._metric_icon')
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium"
                                  :class="selectedMetric === m.key ? 'text-accent' : 'text-gray-900 dark:text-gray-100'"
                                  x-text="metricLabel(m.key)"></span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400"
                                  x-text="latestFor(m.key) != null ? latestFor(m.key) + ' ' + unitLabel(m.key) : @js(__('health.no_entries'))"></span>
                        </span>
                        <template x-if="latestFor(m.key) != null">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                  :class="{
                                      'bg-green-500': classifyLatest(m.key) === 'ok',
                                      'bg-amber-400': classifyLatest(m.key) === 'amber',
                                      'bg-red-500':   classifyLatest(m.key) === 'red',
                                  }"></span>
                        </template>
                    </button>
                </template>

                {{-- Master data row --}}
                <button type="button"
                    @click="selectedMetric = '_master'"
                    class="flex w-full items-center gap-3 px-3.5 py-3 text-left transition-colors hover:bg-accent/5"
                    :class="selectedMetric === '_master' ? 'bg-accent/10' : ''">
                    <span class="ll-chip flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" style="background:#6b7280">
                        <x-icon name="user" class="h-4 w-4 text-white" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium"
                          :class="selectedMetric === '_master' ? 'text-accent' : 'text-gray-900 dark:text-gray-100'">{{ __('health.master_data') }}</span>
                </button>
            </div>
        </aside>

        {{-- Main pane --}}
        <section class="min-w-0 flex-1 overflow-y-auto">

            {{-- Mobile metric selector (the desktop rail is hidden below md) --}}
            <div class="md:hidden -mx-1 mb-3 flex gap-2 overflow-x-auto px-1 pb-1">
                <template x-for="m in metrics" :key="'mob-'+m.key">
                    <button type="button" @click="selectedMetric = m.key"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="selectedMetric === m.key ? 'll-accent border-transparent' : 'border-black/[0.08] dark:border-white/10 text-gray-600 dark:text-gray-300'">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md" :style="'background:' + tintHex(m.tint)">
                            @include('health._metric_icon')
                        </span>
                        <span x-text="metricLabel(m.key)"></span>
                    </button>
                </template>
                <button type="button" @click="selectedMetric = '_master'"
                    class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="selectedMetric === '_master' ? 'll-accent border-transparent' : 'border-black/[0.08] dark:border-white/10 text-gray-600 dark:text-gray-300'">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md" style="background:#6b7280"><x-icon name="user" class="h-3.5 w-3.5 text-white" /></span>
                    {{ __('health.master_data') }}
                </button>
            </div>

            {{-- ===== MASTER DATA VIEW ===== --}}
            <template x-if="selectedMetric === '_master'">
              <div class="ll-card space-y-5">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('health.master_data') }}</h2>

                {{-- Computed derived values --}}
                <div class="flex gap-6">
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.age') }}</span>
                        <span class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="age != null ? age : '—'"></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.bmi') }}</span>
                        <span class="text-xl font-semibold text-gray-900 dark:text-gray-100" x-text="bmi != null ? bmi : '—'"></span>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Date of Birth --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.birthdate') }}</label>
                        <input type="date" x-model="profile.birthdate" @change="saveProfile()"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                    </div>
                    {{-- Height --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.height_cm') }}</label>
                        <input type="number" x-model.number="profile.heightCm" @change="saveProfile()" min="50" max="300" step="0.5"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                    </div>
                    {{-- Sex --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.sex') }}</label>
                        <select x-model="profile.sex" @change="saveProfile()"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                            <option value="">—</option>
                            <option value="m">{{ __('health.sex_m') }}</option>
                            <option value="f">{{ __('health.sex_f') }}</option>
                            <option value="x">{{ __('health.sex_x') }}</option>
                        </select>
                    </div>
                    {{-- Weight goal --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.weight_goal') }}</label>
                        <input type="number" x-model.number="profile.weightGoalKg" @change="saveProfile()" min="20" max="500" step="0.5"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-accent focus:ring-accent">
                    </div>
                </div>

                {{-- Unit toggles --}}
                <div class="space-y-3 border-t border-gray-100 dark:border-gray-800 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('health.units_weight') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="profile.units.weight = 'kg'; saveProfile()"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="profile.units.weight === 'kg' ? 'll-accent' : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'">kg</button>
                        <button type="button" @click="profile.units.weight = 'lb'; saveProfile()"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="profile.units.weight === 'lb' ? 'll-accent' : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'">lb</button>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('health.units_glucose') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="profile.units.glucose = 'mgdl'; saveProfile()"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="profile.units.glucose === 'mgdl' ? 'll-accent' : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'">mg/dL</button>
                        <button type="button" @click="profile.units.glucose = 'mmoll'; saveProfile()"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="profile.units.glucose === 'mmoll' ? 'll-accent' : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'">mmol/L</button>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ __('health.units_temp') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="profile.units.temp = 'c'; saveProfile()"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="profile.units.temp === 'c' ? 'll-accent' : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'">°C</button>
                        <button type="button" @click="profile.units.temp = 'f'; saveProfile()"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="profile.units.temp === 'f' ? 'll-accent' : 'border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'">°F</button>
                    </div>
                </div>
              </div>
            </template>

            {{-- ===== METRIC DETAIL VIEW ===== --}}
            <template x-if="selectedMetric !== '_master'">
              <div class="ll-card space-y-4">
                {{-- Header with metric label + actions --}}
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100"
                            x-text="metricLabel(selectedMetric)"></h2>
                        <p class="text-xs text-gray-400 dark:text-gray-500" x-text="unitLabel(selectedMetric)"></p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="exportCsv(selectedMetric)"
                            class="no-print inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:border-accent hover:text-accent"
                            x-show="entriesFor(selectedMetric).length > 0">
                            <x-icon name="arrow-down-tray" class="h-4 w-4" />
                            {{ __('health.export_csv') }}
                        </button>
                        <button type="button" @click="enterReport()"
                            class="no-print inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:border-accent hover:text-accent">
                            <x-icon name="document-text" class="h-4 w-4" />
                            {{ __('health.report') }}
                        </button>
                        <button type="button" @click="openAdd()"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-lg ll-accent px-3 py-2 text-sm font-medium hover:brightness-105">
                            <x-icon name="plus" class="h-4 w-4" />
                            {{ __('health.add_measurement') }}
                        </button>
                    </div>
                </div>

                {{-- Stats strip --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <template x-if="latestFor(selectedMetric) != null">
                        <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.latest') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="latestFor(selectedMetric)"></p>
                        </div>
                    </template>
                    <template x-if="avgFor(selectedMetric) != null">
                        <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.avg') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="avgFor(selectedMetric)"></p>
                        </div>
                    </template>
                    <template x-if="minFor(selectedMetric) != null">
                        <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.min') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="minFor(selectedMetric)"></p>
                        </div>
                    </template>
                    <template x-if="maxFor(selectedMetric) != null">
                        <div class="rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.max') }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="maxFor(selectedMetric)"></p>
                        </div>
                    </template>
                </div>

                {{-- Range chips + chart --}}
                <div class="space-y-2">
                    {{-- Range selector --}}
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="r in ['7d','30d','90d','1y','all']" :key="r">
                            <button type="button"
                                @click="chartRange = r"
                                class="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                :class="chartRange === r
                                    ? 'bg-accent/10 text-accent'
                                    : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-accent/5 hover:text-accent'"
                                x-text="r">
                            </button>
                        </template>
                    </div>

                    {{-- Chart mount point — renderChart() is driven by $watch in init(), not x-effect --}}
                    <div x-ref="chart"
                         class="w-full overflow-hidden rounded-xl"
                         style="min-height:0">
                    </div>

                    {{-- Empty state when no entries in range --}}
                    <template x-if="entriesInRange(selectedMetric).length === 0 && entriesFor(selectedMetric).length > 0">
                        <div class="flex h-28 items-center justify-center rounded-xl border border-dashed border-gray-200 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500">
                            {{ __('health.no_entries_range') }}
                        </div>
                    </template>
                </div>

                {{-- Entries table --}}
                <div x-show="entriesFor(selectedMetric).length === 0"
                     class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('health.no_entries') }}
                </div>

                <div x-show="entriesFor(selectedMetric).length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-xs text-gray-400 dark:text-gray-500">
                                <th class="py-2 pr-4 font-medium">{{ __('health.date_time') }}</th>
                                <th class="py-2 pr-4 font-medium">{{ __('health.value') }}</th>
                                <th class="py-2 pr-4 font-medium">{{ __('health.note') }}</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="e in entriesFor(selectedMetric)" :key="e.id">
                                <tr class="group border-b border-gray-50 dark:border-gray-900 hover:bg-accent/5">
                                    <td class="py-2.5 pr-4 text-gray-700 dark:text-gray-300 whitespace-nowrap"
                                        x-text="new Date(e.ts).toLocaleString(undefined, {year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'})"></td>
                                    <td class="py-2.5 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                        <span x-text="_displayValue(e.metric, e.v, e.v2)"></span>
                                        <span class="ml-1 text-xs text-gray-400 dark:text-gray-500" x-text="unitLabel(e.metric)"></span>
                                        <span class="ml-1 inline-block h-2 w-2 rounded-full"
                                              :class="{
                                                  'bg-green-500': classify(e.metric, e.v, e.v2) === 'ok',
                                                  'bg-amber-400': classify(e.metric, e.v, e.v2) === 'amber',
                                                  'bg-red-500':   classify(e.metric, e.v, e.v2) === 'red',
                                              }"></span>
                                    </td>
                                    <td class="py-2.5 pr-4 text-gray-500 dark:text-gray-400 max-w-xs truncate" x-text="e.note || ''"></td>
                                    <td class="py-2.5 text-right">
                                        <span class="flex justify-end gap-1 md:invisible md:group-hover:visible">
                                            <button type="button" @click="openEdit(e)" title="{{ __('health.edit_measurement') }}"
                                                class="rounded p-1 text-gray-400 hover:text-accent">
                                                <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            </button>
                                            <button type="button" @click="deleteEntry(e)" title="{{ __('health.delete_confirm') }}"
                                                class="rounded p-1 text-gray-400 hover:text-red-500">
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
              </div>
            </template>

        </section>
      </div>{{-- end main view --}}

      {{-- ===== REPORT VIEW ===== --}}
      <div x-show="view === 'report'" class="health-report mx-auto max-w-4xl px-4 py-6 space-y-8">

        {{-- Report toolbar (hidden when printing) --}}
        <div class="no-print flex items-center justify-between">
            <button type="button" @click="exitReport()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                <x-icon name="arrow-left" class="h-4 w-4" />
                {{ __('health.report_back') }}
            </button>
            <button type="button" @click="window.print()"
                class="inline-flex items-center gap-1.5 rounded-lg ll-accent px-4 py-2 text-sm font-medium hover:brightness-105">
                <x-icon name="printer" class="h-4 w-4" />
                {{ __('health.report_print') }}
            </button>
        </div>

        {{-- Master data block --}}
        <div class="ll-card space-y-4 break-inside-avoid">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('health.report_master_data') }}</h2>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.master_data') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="userName || '—'"></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.age') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="age != null ? age : '—'"></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.bmi') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="bmi != null ? bmi : '—'"></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.height_cm') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="profile.heightCm ? profile.heightCm + ' cm' : '—'"></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.metric_weight') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="latestFor('weight') != null ? (latestFor('weight') + ' ' + unitLabel('weight')) : '—'"></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('health.sex') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100"
                        x-text="profile.sex === 'm' ? @js(__('health.sex_m')) : (profile.sex === 'f' ? @js(__('health.sex_f')) : (profile.sex === 'x' ? @js(__('health.sex_x')) : '—'))">
                    </dd>
                </div>
            </dl>
            <p class="text-xs text-gray-400 dark:text-gray-500 pt-2 border-t border-gray-100 dark:border-gray-800">
                {{ __('health.report_generated') }}:
                <span x-text="reportGeneratedAt ? new Date(reportGeneratedAt).toLocaleString() : ''"></span>
            </p>
        </div>

        {{-- No-data fallback: shown when no metric has any entries --}}
        <template x-if="metrics.every(m => entriesFor(m.key).length === 0)">
            <p class="text-center text-sm text-gray-400 dark:text-gray-500 py-10">{{ __('health.report_no_entries') }}</p>
        </template>

        {{-- Per-metric blocks --}}
        <template x-for="m in metrics" :key="m.key">
            <div x-show="entriesFor(m.key).length > 0" class="ll-card space-y-4 break-inside-avoid">
                {{-- Metric heading --}}
                <div class="flex items-center gap-2">
                    <span class="ll-chip flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                          :style="'background:' + tintHex(m.tint)">
                        <template x-if="m.icon === 'heart'"><svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg></template>
                        <template x-if="m.icon === 'beaker'"><svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 2.25v7.5L18 15.75H6L9.75 9.75V2.25M10.5 2.25h3M3.75 21h16.5" /></svg></template>
                        <template x-if="m.icon === 'thermometer'"><svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m0 6a3 3 0 100-6 3 3 0 000 6zm0-9V3m0 6a3 3 0 013 3m-6 0a3 3 0 013-3" /></svg></template>
                        <template x-if="m.icon === 'scale'"><svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5M12 3c-1.2 0-2.4.6-3 1.5L3 16.5h18L15 4.5C14.4 3.6 13.2 3 12 3zm0 1.5L6 16.5m6-12l6 12M3 16.5h18v1.5a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-1.5z" /></svg></template>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="metricLabel(m.key)"></h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500" x-text="unitLabel(m.key)"></p>
                    </div>
                </div>

                {{-- Chart mount point (rendered by renderReport()) --}}
                <div class="w-full overflow-hidden rounded-xl report-chart-wrap"
                     style="min-height:0"
                     x-show="entriesInRange(m.key).length > 0"
                     :data-report-chart="m.key">
                    {{-- uPlot instance mounted here by renderReport() --}}
                </div>

                {{-- Latest 30 entries table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 text-gray-400 dark:text-gray-500">
                                <th class="py-1.5 pr-3 font-medium">{{ __('health.report_date') }}</th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('health.report_value') }}</th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('health.report_status') }}</th>
                                <th class="py-1.5 pr-3 font-medium">{{ __('health.report_note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="e in entriesFor(m.key).slice(0,30)" :key="e.id">
                                <tr class="border-b border-gray-50 dark:border-gray-900">
                                    <td class="py-1.5 pr-3 text-gray-600 dark:text-gray-400 whitespace-nowrap"
                                        x-text="new Date(e.ts).toLocaleString(undefined, {year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'})"></td>
                                    <td class="py-1.5 pr-3 font-medium text-gray-900 dark:text-gray-100">
                                        <span x-text="_displayValue(e.metric, e.v, e.v2)"></span>
                                        <span class="ml-1 text-gray-400" x-text="unitLabel(e.metric)"></span>
                                    </td>
                                    <td class="py-1.5 pr-3">
                                        <span class="inline-flex items-center gap-1">
                                            <span class="h-2 w-2 rounded-full inline-block"
                                                  :class="{
                                                      'bg-green-500': classify(e.metric, e.v, e.v2) === 'ok',
                                                      'bg-amber-400': classify(e.metric, e.v, e.v2) === 'amber',
                                                      'bg-red-500':   classify(e.metric, e.v, e.v2) === 'red',
                                                  }"></span>
                                            <span class="text-gray-600 dark:text-gray-400"
                                                  x-text="classify(e.metric, e.v, e.v2) === 'ok' ? @js(__('health.report_status_ok')) : (classify(e.metric, e.v, e.v2) === 'amber' ? @js(__('health.report_status_amber')) : @js(__('health.report_status_red')))"></span>
                                        </span>
                                    </td>
                                    <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400 max-w-xs truncate" x-text="e.note || ''"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Reference range note --}}
                <template x-if="_referenceNote(m.key)">
                    <p class="text-xs text-gray-400 dark:text-gray-500 pt-2 border-t border-gray-100 dark:border-gray-800">
                        {{ __('health.report_reference') }}:
                        <span x-text="_referenceNote(m.key)"></span>
                    </p>
                </template>
            </div>
        </template>

      </div>{{-- end report view --}}

      </div>{{-- end state=ready wrapper --}}
    </template>

    {{-- ===== ADD / EDIT MEASUREMENT MODAL ===== --}}
    <template x-if="editorOpen">
      <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center p-4"
           @keydown.escape.window="closeEditor()">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="closeEditor()"></div>
        <div class="relative w-full max-w-sm rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-5 shadow-xl space-y-4">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                <span x-text="editing ? @js(__('health.edit_measurement')) : @js(__('health.add_measurement'))"></span>
            </h3>

            {{-- Metric selector (only for new entries) --}}
            <template x-if="! editing">
              <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.value') }}</label>
                <select x-model="_form.metric"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm focus:border-accent focus:ring-accent">
                    <template x-for="m in metrics" :key="m.key">
                        <option :value="m.key" x-text="metricLabel(m.key)"></option>
                    </template>
                </select>
              </div>
            </template>

            {{-- Value fields --}}
            <template x-if="_form.metric !== 'bp'">
              <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {{ __('health.value') }}
                    <span x-text="'(' + unitLabel(_form.metric) + ')'"></span>
                </label>
                <input type="number" x-model="_form.v" step="0.1" min="0"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm focus:border-accent focus:ring-accent">
              </div>
            </template>

            <template x-if="_form.metric === 'bp'">
              <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.systolic') }}</label>
                    <input type="number" x-model="_form.v" step="1" min="0"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm focus:border-accent focus:ring-accent">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.diastolic') }}</label>
                    <input type="number" x-model="_form.v2" step="1" min="0"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm focus:border-accent focus:ring-accent">
                </div>
              </div>
            </template>

            {{-- Date & time --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.date_time') }}</label>
                <input type="datetime-local" x-model="_form.ts"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm focus:border-accent focus:ring-accent">
            </div>

            {{-- Note --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ __('health.note') }}</label>
                <input type="text" x-model="_form.note"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-[#1c1c1e] px-3 py-2 text-sm focus:border-accent focus:ring-accent">
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="closeEditor()"
                    class="rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    {{ __('common.cancel') }}
                </button>
                <button type="button" @click="saveEditor()"
                    class="rounded-lg ll-accent px-4 py-2 text-sm font-medium hover:brightness-105">
                    {{ __('health.save') }}
                </button>
            </div>
        </div>
      </div>
    </template>

  </div>
</x-layouts.app>
