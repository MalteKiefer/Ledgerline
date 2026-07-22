<x-layouts.app :title="__('explore.title')">
  <div x-data="explore({
        styleUrl: '{{ url('/maps/style') }}',
        uploadUrl: '{{ url('/explore/upload') }}',
        rawBase: '{{ url('/explore/raw') }}',
        usageUrl: '{{ url('/explore/usage') }}',
        token: '{{ csrf_token() }}',
     }, {
        loadFailed: @js(__('explore.load_failed')),
        mapUnavailable: @js(__('explore.map_unavailable')),
        importFailed: @js(__('explore.import_failed')),
        kmzNoKml: @js(__('explore.kmz_no_kml')),
        matched: @js(__('explore.matched')),
        deleteTrackConfirm: @js(__('explore.delete_track_confirm')),
        sourceExif: @js(__('explore.source_exif')),
        sourceInterpolated: @js(__('explore.source_interpolated')),
        sourceManual: @js(__('explore.source_manual')),
        sourceNone: @js(__('explore.source_none')),
        elevation: @js(__('explore.elevation')),
        unitKm: @js(__('explore.unit_km')),
        unitM: @js(__('explore.unit_m')),
        unitKmh: @js(__('explore.unit_kmh')),
     })">

    {{-- Zero-knowledge gate: tracks + couplings decrypt with the vault key. --}}
    @include('vault._panel', ['serverConfigured' => \App\Models\Vault::current() !== null])

    <x-page-heading :title="__('explore.title')" :subtitle="__('explore.subtitle')">
      <x-slot:actions>
        <template x-if="state === 'ready'">
          <div class="flex flex-wrap items-center gap-2">
            {{-- View toggle (iOS segmented control) --}}
            <div class="inline-flex rounded-xl bg-black/[0.04] dark:bg-white/10 p-0.5">
              <button type="button" @click="view = 'media'"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                :class="view === 'media' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-600 dark:text-gray-400'">
                <x-icon name="photo" class="h-4 w-4" />
                <span>{{ __('explore.view_media') }}</span>
              </button>
              <button type="button" @click="view = 'tracks'"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                :class="view === 'tracks' ? 'bg-white dark:bg-[#2c2c2e] text-accent shadow-sm' : 'text-gray-600 dark:text-gray-400'">
                <x-icon name="route" class="h-4 w-4" />
                <span>{{ __('explore.view_tracks') }}</span>
              </button>
            </div>

            {{-- Import --}}
            <button type="button" @click="$refs.file.click()" :disabled="importing"
              class="inline-flex min-h-9 items-center gap-1.5 rounded-xl ll-accent px-3 py-1.5 text-sm font-medium hover:brightness-105 disabled:opacity-50">
              <x-icon name="arrow-up-tray" class="h-4 w-4" />
              <span x-text="importing ? @js(__('explore.importing')) : @js(__('explore.import'))"></span>
            </button>
            <input type="file" x-ref="file" class="hidden" accept=".gpx,.kml,.kmz,.tcx,.fit" multiple @change="onImport($event)">

            {{-- Settings --}}
            <button type="button" @click="settingsOpen = true"
              class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-black/[0.08] dark:border-white/10 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5"
              :title="@js(__('explore.settings'))" :aria-label="@js(__('explore.settings'))">
              <x-icon name="clock" class="h-4 w-4" />
            </button>
          </div>
        </template>
      </x-slot:actions>
    </x-page-heading>

    {{-- Locked --}}
    <template x-if="state === 'locked'">
      <div class="mx-auto mt-16 max-w-md ll-card !p-8 text-center">
        <x-icon name="lock-closed" class="mx-auto h-8 w-8 text-gray-400" />
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('explore.locked') }}</p>
        <button type="button" @click="$dispatch('vault-panel')"
          class="mt-5 inline-flex min-h-11 items-center gap-1.5 rounded-md ll-accent px-4 py-2 text-sm font-medium hover:brightness-105">
          <x-icon name="lock-open" class="h-4 w-4" />
          <span x-text="$store.vault.configured ? @js(__('vault.unlock')) : @js(__('vault.setup'))"></span>
        </button>
      </div>
    </template>

    <template x-if="state === 'error'">
      <p class="mx-auto mt-16 max-w-md rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 p-6 text-center text-sm text-red-700 dark:text-red-300">{{ __('explore.load_failed') }}</p>
    </template>

    {{-- Ready --}}
    <template x-if="state === 'ready'">
      <div class="mt-4">
        <p x-show="error" x-text="error" x-cloak class="mb-3 rounded-lg border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 px-3 py-2 text-sm text-red-700 dark:text-red-300"></p>

        <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
          {{-- Map --}}
          <div class="ll-card !p-0 overflow-hidden">
            <div x-ref="map" class="h-[calc(100dvh-16rem)] min-h-80 w-full"></div>
          </div>

          {{-- Sidebar: media list (media view) or tracks list + elevation (tracks view) --}}
          <div class="min-w-0 space-y-4">

            {{-- ===== MEDIA VIEW ===== --}}
            <div x-show="view === 'media'" class="ll-card !p-0 overflow-hidden">
              <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('explore.view_media') }}</h2>
                <button type="button" @click="matchPhotos()" :disabled="busy || ! tracks.length"
                  class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium text-accent hover:bg-accent/5 disabled:opacity-40">
                  <x-icon name="arrow-path" class="h-3.5 w-3.5" />
                  <span x-text="busy ? @js(__('explore.matching')) : @js(__('explore.match_photos'))"></span>
                </button>
              </div>

              <template x-if="! placedMedia.length">
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('explore.empty_media') }}</p>
              </template>

              <div class="max-h-[calc(100dvh-22rem)] overflow-y-auto divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="m in placedMedia" :key="m.id">
                  <div class="flex items-center gap-3 px-4 py-2.5">
                    <div class="h-10 w-10 shrink-0 rounded-lg bg-black/[0.06] dark:bg-white/10 bg-cover bg-center"
                         :style="thumbs[m.id] ? ('background-image:url(\'' + thumbs[m.id] + '\')') : ''"
                         x-init="$nextTick(() => _thumbFor(m))"></div>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm text-gray-900 dark:text-gray-100" x-text="m.name || m.id"></p>
                      <p class="text-xs text-gray-500 dark:text-gray-400" x-text="couplingLabel(m.id)"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                      <button type="button" @click="assignFor = (assignFor === m.id ? null : m.id)"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-accent/5 hover:text-accent"
                        :title="@js(__('explore.assign_to_track'))" :aria-label="@js(__('explore.assign_to_track'))">
                        <x-icon name="route" class="h-4 w-4" />
                      </button>
                      <button type="button" x-show="couplings[m.id]" @click="clearCoupling(m.id)"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-red-500/10 hover:text-red-500"
                        :title="@js(__('explore.clear_coupling'))" :aria-label="@js(__('explore.clear_coupling'))">
                        <x-icon name="x-mark" class="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </template>

                {{-- Manual assign picker for the selected media --}}
                <template x-if="assignFor">
                  <div class="bg-accent/5 px-4 py-3">
                    <p class="mb-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('explore.assign_to_track') }}</p>
                    <div class="space-y-1">
                      <template x-for="t in tracks" :key="t.id">
                        <button type="button" @click="assignToTrack(assignFor, t.id)"
                          class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-white/70 dark:hover:bg-white/10">
                          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + trackColor(t)"></span>
                          <span class="truncate" x-text="t.name"></span>
                        </button>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
            </div>

            {{-- ===== TRACKS VIEW ===== --}}
            <div x-show="view === 'tracks'" class="space-y-4">
              <div class="ll-card !p-0 overflow-hidden">
                <div class="border-b border-black/[0.06] dark:border-white/10 px-4 py-3">
                  <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('explore.tracks_heading') }}</h2>
                </div>

                <template x-if="! tracks.length">
                  <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('explore.empty_tracks') }}</p>
                </template>

                <div class="max-h-64 overflow-y-auto divide-y divide-black/[0.06] dark:divide-white/10">
                  <template x-for="t in tracks" :key="t.id">
                    <button type="button" @click="selectTrack(t.id)"
                      class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-accent/5"
                      :class="selectedTrackId === t.id ? 'bg-accent/10' : ''">
                      <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + trackColor(t)"></span>
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-gray-900 dark:text-gray-100" x-text="t.name"></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                          <span x-text="fmtDistance(t.stats.distanceM)"></span>
                          <span x-show="t.stats.durationTotalS"> · <span x-text="fmtDuration(t.stats.durationTotalS)"></span></span>
                        </p>
                      </div>
                      <button type="button" @click.stop="deleteTrack(t)"
                        class="rounded-md p-1.5 text-gray-400 hover:bg-red-500/10 hover:text-red-500"
                        :title="@js(__('explore.delete_track'))" :aria-label="@js(__('explore.delete_track'))">
                        <x-icon name="trash" class="h-4 w-4" />
                      </button>
                    </button>
                  </template>
                </div>
              </div>

              {{-- Selected-track stats + elevation profile --}}
              <template x-if="selectedTrack">
                <div class="ll-card">
                  <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="selectedTrack.name"></h3>
                  <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.distance') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDistance(selectedTrack.stats.distanceM)"></dd></div>
                    <div x-show="selectedTrack.stats.durationTotalS"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.duration') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDuration(selectedTrack.stats.durationTotalS)"></dd></div>
                    <div x-show="selectedTrack.stats.ascentM"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.ascent') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtEle(selectedTrack.stats.ascentM)"></dd></div>
                    <div x-show="selectedTrack.stats.descentM"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.descent') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtEle(selectedTrack.stats.descentM)"></dd></div>
                    <div x-show="selectedTrack.stats.maxSpeedMps"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.max_speed') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtSpeed(selectedTrack.stats.maxSpeedMps)"></dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.points') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="selectedTrack.stats.pointCount"></dd></div>
                  </dl>

                  <div class="mt-4">
                    <p class="mb-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('explore.elevation_profile') }}</p>
                    <div x-ref="elevation" class="w-full"></div>
                  </div>
                </div>
              </template>
            </div>

          </div>
        </div>

        {{-- Settings modal --}}
        <template x-teleport="body">
          <div x-show="settingsOpen" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="settingsOpen = false">
            <div class="absolute inset-0 bg-gray-900/40" @click="settingsOpen = false"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] p-6 shadow-xl">
              <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('explore.settings') }}</h3>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('explore.settings_hint') }}</p>
              <div class="mt-4 space-y-3">
                <label class="block">
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('explore.time_tolerance') }}</span>
                  <input type="number" min="0" x-model.number="settings.couplingTimeToleranceS" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
                <label class="block">
                  <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('explore.distance_tolerance') }}</span>
                  <input type="number" min="0" x-model.number="settings.couplingDistanceToleranceM" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-accent focus:ring-accent">
                </label>
              </div>
              <div class="mt-5 flex justify-end gap-3">
                <button type="button" @click="settingsOpen = false" class="rounded-md border border-gray-300 dark:border-white/10 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">{{ __('explore.close') }}</button>
                <button type="button" @click="saveSettings()" class="rounded-xl ll-accent px-4 py-2 text-sm font-medium">{{ __('explore.save') }}</button>
              </div>
            </div>
          </div>
        </template>

      </div>
    </template>

  </div>
</x-layouts.app>
