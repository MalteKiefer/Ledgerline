<x-layouts.app :title="__('explore.title')">
  <div x-data="explore({
        uploadUrl: '{{ url('/explore/upload') }}',
        rawBase: '{{ url('/explore/raw') }}',
        galleryRawBase: '{{ url('/gallery/raw') }}',
        usageUrl: '{{ url('/explore/usage') }}',
        deleteUrl: '{{ url('/explore/blob') }}',
        routeUrl: '{{ url('/maps/route') }}',
        geocodeUrl: '{{ url('/gallery/geocode') }}',
        resolveUrl: '{{ url('/maps/resolve') }}',
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
        photoNoPosition: @js(__('explore.photo_no_position')),
        elevation: @js(__('explore.elevation')),
        surfaces: @js(__('explore.surface')),
        unitKm: @js(__('explore.unit_km')),
        unitM: @js(__('explore.unit_m')),
        unitKmh: @js(__('explore.unit_kmh')),
        save: @js(__('explore.save')),
        routeName: @js(__('explore.route_name')),
        plannedRoute: @js(__('explore.planned_route_default')),
        routeFallback: @js(__('explore.auto_route_fallback')),
        routeRateLimited: @js(__('explore.auto_route_rate_limited')),
        routeTooMany: @js(__('explore.auto_route_too_many')),
        searchNotFound: @js(__('explore.search_not_found')),
        searchFailed: @js(__('explore.search_failed')),
        searchResult: @js(__('explore.search_result')),
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
            {{-- Search: place / POI / coordinates / Google-Maps link. Coordinates
                 and long Google links are parsed locally; a place query hits the
                 geocoder and a short google link hits the resolver. --}}
            <div class="relative z-10 border-b border-black/[0.06] dark:border-white/10 p-2">
              <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><x-icon name="magnifying-glass" class="h-4 w-4" /></span>
                <input type="search" x-model="searchQuery" @keydown.enter.prevent="runSearch()"
                  :placeholder="@js(__('explore.search_ph'))"
                  class="w-full rounded-xl border border-black/[0.08] dark:border-white/10 bg-white dark:bg-[#1c1c1e] py-2 pl-9 pr-24 text-sm focus:border-accent focus:ring-accent">
                <div class="absolute right-1.5 top-1/2 flex -translate-y-1/2 items-center gap-1">
                  <button type="button" x-show="searchQuery.trim()" @click="searchQuery=''; searchResults=[]; searchMsg=''" class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 transition hover:bg-black/5 dark:hover:bg-white/10" :title="@js(__('common.cancel'))"><x-icon name="x-mark" class="h-4 w-4" /></button>
                  <button type="button" @click="runSearch()" :disabled="searching || !searchQuery.trim()"
                    class="rounded-lg ll-accent px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                    x-text="searching ? '…' : @js(__('explore.search_go'))"></button>
                </div>
              </div>
              {{-- Geocoder result dropdown --}}
              <div x-show="searchResults.length" x-cloak class="mt-1 max-h-56 overflow-y-auto rounded-xl border border-black/[0.08] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-lg">
                <template x-for="(r, i) in searchResults" :key="i">
                  <button type="button" @click="pickSearchResult(r)" class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm transition hover:bg-accent/5">
                    <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
                    <span class="min-w-0 flex-1 truncate" x-text="r.display"></span>
                  </button>
                </template>
              </div>
              <p x-show="searchMsg" x-cloak class="mt-1 px-1 text-xs text-gray-500 dark:text-gray-400" x-text="searchMsg"></p>
            </div>
            {{-- `isolate` (+ z-0) confines Leaflet's internal z-indexes (panes/controls
                 up to ~1000) to a local stacking context so the map can't paint over
                 the z-40 nav dropdown. --}}
            <div x-ref="map" class="relative z-0 isolate h-[calc(100dvh-19rem)] min-h-80 w-full"></div>
          </div>

          {{-- Sidebar: media list (media view) or tracks list + elevation (tracks view) --}}
          <div class="min-w-0 space-y-4">

            {{-- ===== MEDIA VIEW ===== --}}
            <div x-show="view === 'media'" class="ll-card !p-0 overflow-hidden">
              <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                  {{ __('explore.view_media') }}
                  <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400" x-text="'(' + placedCount() + ')'"></span>
                </h2>
                <button type="button" @click="matchPhotos()" :disabled="busy || ! tracks.length"
                  class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium text-accent hover:bg-accent/5 disabled:opacity-40">
                  <x-icon name="arrow-path" class="h-3.5 w-3.5" />
                  <span x-text="busy ? @js(__('explore.matching')) : @js(__('explore.match_photos'))"></span>
                </button>
              </div>

              {{-- Search --}}
              <div x-show="placedMedia.length" class="border-b border-black/[0.06] dark:border-white/10 px-3 py-2">
                <div class="relative">
                  <x-icon name="magnifying-glass" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                  <input type="search" x-model="mediaQuery" placeholder="{{ __('explore.search_media') }}"
                    class="w-full rounded-lg border-0 bg-black/[0.04] dark:bg-white/10 py-1.5 pl-8 pr-3 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-2 focus:ring-accent">
                </div>
              </div>

              <template x-if="! placedMedia.length">
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('explore.empty_media') }}</p>
              </template>
              <template x-if="placedMedia.length && ! filteredMedia.length">
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('explore.no_search_results') }}</p>
              </template>

              <div class="max-h-[calc(100dvh-22rem)] overflow-y-auto divide-y divide-black/[0.06] dark:divide-white/10">
                <template x-for="m in filteredMedia" :key="m.id">
                  <div class="flex items-center gap-3 px-4 py-2.5">
                    <div class="h-10 w-10 shrink-0 rounded-lg bg-black/[0.06] dark:bg-white/10 bg-cover bg-center"
                         :style="thumbs[m.id] ? ('background-image:url(\'' + thumbs[m.id] + '\')') : ''"
                         x-init="$nextTick(() => _thumbFor(m))"></div>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm text-gray-900 dark:text-gray-100" x-text="m.name || m.id"></p>
                      <p class="text-xs text-gray-500 dark:text-gray-400" x-text="couplingLabel(m.id)"></p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                      <button type="button" @click="openAssign(m.id)"
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

              </div>
            </div>

            {{-- ===== TRACKS VIEW ===== --}}
            <div x-show="view === 'tracks'" class="space-y-4">
              <div class="ll-card !p-0 overflow-hidden">
                <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-4 py-3">
                  <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('explore.tracks_heading') }}
                    <span class="ml-1 text-xs font-normal text-gray-500 dark:text-gray-400" x-text="'(' + tracks.length + ')'"></span>
                  </h2>
                  <button type="button" @click="togglePlan()"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium text-accent hover:bg-accent/5"
                    :class="planning ? 'bg-accent/10' : ''">
                    <x-icon name="map-pin" class="h-3.5 w-3.5" />
                    <span>{{ __('explore.plan_tour') }}</span>
                  </button>
                </div>

                {{-- Planning toolbar --}}
                <template x-if="planning">
                  <div class="border-b border-black/[0.06] dark:border-white/10 bg-accent/5 px-4 py-3">
                    <p class="text-xs text-gray-700 dark:text-gray-300">{{ __('explore.planning_hint') }}</p>
                    {{-- Opt-in auto-routing: snap waypoints to real paths (default OFF). --}}
                    <label class="mt-2 flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                      <input type="checkbox" x-model="autoRoute" @change="toggleAutoRoute()" class="rounded">
                      <span>{{ __('explore.auto_route') }}</span>
                      <span x-show="routing" class="text-accent">{{ __('explore.auto_route_routing') }}</span>
                    </label>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ __('explore.auto_route_hint') }}</p>

                    {{-- Live plan stats: waypoints, distance, (routed) duration + ascent/descent. --}}
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                      <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('explore.plan_waypoints') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="planPoints.length"></dd>
                      </div>
                      <div x-show="planPoints.length >= 2">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('explore.plan_distance') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="fmtDistance(planDistanceM)"></dd>
                      </div>
                      <div x-show="planDurationS !== null">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('explore.plan_duration') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="fmtDuration(planDurationS)"></dd>
                      </div>
                      <div x-show="planAscentM !== null">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('explore.ascent') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="fmtEle(planAscentM)"></dd>
                      </div>
                      <div x-show="planDescentM !== null">
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('explore.descent') }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="fmtEle(planDescentM)"></dd>
                      </div>
                    </dl>

                    {{-- Live elevation profile (GraphHopper only) or a subtle hint. --}}
                    <div class="mt-3" x-show="planPoints.length >= 2 && autoRoute && ! routing">
                      <p class="mb-1 text-[11px] font-medium text-gray-600 dark:text-gray-400">{{ __('explore.elevation_profile') }}</p>
                      <div x-show="planHasElevation" x-ref="planElevation" class="w-full"></div>
                      <p x-show="! planHasElevation" x-cloak class="text-[11px] text-gray-400 dark:text-gray-500">{{ __('explore.no_elevation') }}</p>
                    </div>

                    {{-- Surface breakdown (GraphHopper only). --}}
                    <div class="mt-3" x-show="planSurfaces.length">
                      <p class="mb-1 text-[11px] font-medium text-gray-600 dark:text-gray-400">{{ __('explore.surfaces') }}</p>
                      <div class="flex flex-wrap gap-1.5">
                        <template x-for="s in planSurfaces" :key="s.surface">
                          <span class="inline-flex items-center gap-1 rounded-full bg-black/[0.04] dark:bg-white/10 px-2 py-0.5 text-[11px] text-gray-700 dark:text-gray-300">
                            <span x-text="surfaceLabel(s.surface)"></span>
                            <span class="text-gray-400 dark:text-gray-500" x-text="fmtDistance(s.distM)"></span>
                          </span>
                        </template>
                      </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                      <button type="button" @click="savePlan()" :disabled="planPoints.length < 2"
                        class="inline-flex items-center gap-1.5 rounded-lg ll-accent px-3 py-1.5 text-xs font-medium disabled:opacity-40">
                        <x-icon name="check" class="h-3.5 w-3.5" />
                        <span>{{ __('explore.save_route') }}</span>
                      </button>
                      <button type="button" @click="undoWaypoint()" :disabled="! planPoints.length"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-black/[0.08] dark:border-white/10 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-accent/5 disabled:opacity-40">
                        <x-icon name="arrow-uturn-left" class="h-3.5 w-3.5" />
                        <span>{{ __('explore.undo_point') }}</span>
                      </button>
                      <button type="button" @click="cancelPlan()"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">{{ __('explore.cancel') }}</button>
                    </div>
                  </div>
                </template>

                {{-- Search + list + empty states are hidden while planning so the
                     in-progress route isn't cluttered by existing tours. --}}
                <div x-show="tracks.length && ! planning" class="border-b border-black/[0.06] dark:border-white/10 px-3 py-2">
                  <div class="relative">
                    <x-icon name="magnifying-glass" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input type="search" x-model="trackQuery" placeholder="{{ __('explore.search_tracks') }}"
                      class="w-full rounded-lg border-0 bg-black/[0.04] dark:bg-white/10 py-1.5 pl-8 pr-3 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-2 focus:ring-accent">
                  </div>
                </div>

                <template x-if="! tracks.length && ! planning">
                  <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('explore.empty_tracks') }}</p>
                </template>
                <template x-if="tracks.length && ! filteredTracks.length && ! planning">
                  <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('explore.no_search_results') }}</p>
                </template>

                <div x-show="! planning" class="max-h-[calc(100dvh-24rem)] overflow-y-auto divide-y divide-black/[0.06] dark:divide-white/10">
                  <template x-for="t in filteredTracks" :key="t.id">
                    <div class="group flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-accent/5"
                      :class="selectedTrackId === t.id ? 'bg-accent/10' : ''">
                      <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + trackColor(t)"></span>

                      {{-- Inline rename editor --}}
                      <template x-if="renamingId === t.id">
                        <div class="flex min-w-0 flex-1 items-center gap-1.5" @click.stop>
                          <input type="text" x-ref="renameInput" x-model="renameValue"
                            @keydown.enter.prevent="saveRename(t)" @keydown.escape.prevent="cancelRename()"
                            class="min-w-0 flex-1 rounded-md border-0 bg-black/[0.04] dark:bg-white/10 px-2 py-1 text-sm text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-accent">
                          <button type="button" @click="saveRename(t)" class="rounded-md p-1 text-accent hover:bg-accent/10" :aria-label="@js(__('explore.save'))"><x-icon name="check" class="h-4 w-4" /></button>
                          <button type="button" @click="cancelRename()" class="rounded-md p-1 text-gray-400 hover:bg-black/5 dark:hover:bg-white/10" :aria-label="@js(__('explore.cancel'))"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                      </template>

                      {{-- Row label (click → detail) --}}
                      <template x-if="renamingId !== t.id">
                        <button type="button" @click="openDetail(t.id)" class="min-w-0 flex-1 text-left">
                          <p class="truncate text-sm text-gray-900 dark:text-gray-100" x-text="t.name"></p>
                          <p class="text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="fmtDistance(t.stats.distanceM)"></span>
                            <span x-show="t.stats.durationTotalS"> · <span x-text="fmtDuration(t.stats.durationTotalS)"></span></span>
                          </p>
                        </button>
                      </template>

                      <div class="flex shrink-0 items-center gap-0.5" x-show="renamingId !== t.id">
                        <button type="button" @click.stop="startRename(t)"
                          class="rounded-md p-1.5 text-gray-400 hover:bg-accent/5 hover:text-accent md:opacity-0 md:group-hover:opacity-100"
                          :title="@js(__('explore.rename_track'))" :aria-label="@js(__('explore.rename_track'))">
                          <x-icon name="pencil" class="h-4 w-4" />
                        </button>
                        <button type="button" @click.stop="deleteTrack(t)"
                          class="rounded-md p-1.5 text-gray-400 hover:bg-red-500/10 hover:text-red-500 md:opacity-0 md:group-hover:opacity-100"
                          :title="@js(__('explore.delete_track'))" :aria-label="@js(__('explore.delete_track'))">
                          <x-icon name="trash" class="h-4 w-4" />
                        </button>
                        <x-icon name="chevron-right" class="h-4 w-4 text-gray-300 dark:text-gray-600" />
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>

            {{-- ===== DETAIL VIEW ===== --}}
            <div x-show="view === 'detail'" class="space-y-4">
              <template x-if="selectedTrack">
                <div class="ll-card">
                  {{-- Header: back + name (editable) + actions --}}
                  <div class="flex items-start gap-2">
                    <button type="button" @click="backToList()"
                      class="mt-0.5 rounded-lg p-1.5 text-gray-500 hover:bg-accent/5 hover:text-accent"
                      :title="@js(__('explore.back'))" :aria-label="@js(__('explore.back'))">
                      <x-icon name="chevron-left" class="h-5 w-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                      <template x-if="renamingId === selectedTrack.id">
                        <div class="flex items-center gap-1.5">
                          <input type="text" x-ref="renameInput" x-model="renameValue"
                            @keydown.enter.prevent="saveRename(selectedTrack)" @keydown.escape.prevent="cancelRename()"
                            class="min-w-0 flex-1 rounded-md border-0 bg-black/[0.04] dark:bg-white/10 px-2 py-1 text-base font-semibold text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-accent">
                          <button type="button" @click="saveRename(selectedTrack)" class="rounded-md p-1 text-accent hover:bg-accent/10" :aria-label="@js(__('explore.save'))"><x-icon name="check" class="h-4 w-4" /></button>
                          <button type="button" @click="cancelRename()" class="rounded-md p-1 text-gray-400 hover:bg-black/5 dark:hover:bg-white/10" :aria-label="@js(__('explore.cancel'))"><x-icon name="x-mark" class="h-4 w-4" /></button>
                        </div>
                      </template>
                      <template x-if="renamingId !== selectedTrack.id">
                        <h3 class="truncate text-base font-semibold text-gray-900 dark:text-gray-100" x-text="selectedTrack.name"></h3>
                      </template>
                    </div>
                    {{-- Actions: rename / GPX download / delete, in a 3-dot menu --}}
                    <div class="relative mt-0.5 shrink-0" x-data="{ open: false }" @keydown.escape.window="open = false">
                      <button type="button" @click="open = ! open"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-accent/5 hover:text-accent"
                        :title="@js(__('explore.track_actions'))" :aria-label="@js(__('explore.track_actions'))" :aria-expanded="open">
                        <x-icon name="ellipsis" class="h-5 w-5" />
                      </button>
                      <div x-show="open" x-cloak @click.outside="open = false"
                        class="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-xl border border-black/[0.08] dark:border-white/10 bg-white dark:bg-[#1c1c1e] py-1 shadow-lg">
                        <button type="button" @click="open = false; startRename(selectedTrack)"
                          class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 transition hover:bg-accent/5 hover:text-accent">
                          <x-icon name="pencil" class="h-4 w-4" />{{ __('explore.edit_name') }}
                        </button>
                        <button type="button" @click="open = false; downloadGpx(selectedTrack)"
                          class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 transition hover:bg-accent/5 hover:text-accent">
                          <x-icon name="arrow-down-tray" class="h-4 w-4" />{{ __('explore.download_gpx') }}
                        </button>
                        <button type="button" @click="open = false; deleteTrack(selectedTrack)"
                          class="flex w-full items-center gap-2.5 border-t border-black/[0.06] dark:border-white/10 px-3 py-2 text-left text-sm text-red-600 dark:text-red-400 transition hover:bg-red-500/10">
                          <x-icon name="trash" class="h-4 w-4" />{{ __('explore.delete_track') }}
                        </button>
                      </div>
                    </div>
                  </div>

                  {{-- Full stats table --}}
                  <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.distance') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDistance(selectedTrack.stats.distanceM)"></dd></div>
                    <div><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.points') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="selectedTrack.stats.pointCount"></dd></div>
                    <div x-show="selectedTrack.stats.durationTotalS"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.duration') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDuration(selectedTrack.stats.durationTotalS)"></dd></div>
                    <div x-show="selectedTrack.stats.durationMovingS"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.moving_time') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDuration(selectedTrack.stats.durationMovingS)"></dd></div>
                    <div x-show="selectedTrack.stats.ascentM"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.ascent') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtEle(selectedTrack.stats.ascentM)"></dd></div>
                    <div x-show="selectedTrack.stats.descentM"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.descent') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtEle(selectedTrack.stats.descentM)"></dd></div>
                    <div x-show="selectedTrack.stats.minEleM != null"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.min_elevation') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtEle(selectedTrack.stats.minEleM)"></dd></div>
                    <div x-show="selectedTrack.stats.maxEleM != null"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.max_elevation') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtEle(selectedTrack.stats.maxEleM)"></dd></div>
                    <div x-show="selectedTrack.stats.avgSpeedMps"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.avg_speed') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtSpeed(selectedTrack.stats.avgSpeedMps)"></dd></div>
                    <div x-show="selectedTrack.stats.maxSpeedMps"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.max_speed') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtSpeed(selectedTrack.stats.maxSpeedMps)"></dd></div>
                    <div x-show="caloriesFor(selectedTrack) != null"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.calories') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="caloriesFor(selectedTrack) + ' kcal'"></dd></div>
                    <div x-show="selectedTrack.startedAt"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.started') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDateTime(selectedTrack.startedAt)"></dd></div>
                    <div x-show="selectedTrack.endedAt"><dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('explore.ended') }}</dt><dd class="text-gray-900 dark:text-gray-100" x-text="fmtDateTime(selectedTrack.endedAt)"></dd></div>
                  </dl>

                  {{-- Same-route comparison: other tours over the same route, with
                       pace + calories, the fastest one flagged. --}}
                  <template x-if="routeComparison.length">
                    <div class="mt-5">
                      <p class="mb-2 text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('explore.same_route') }}</p>
                      <div class="overflow-x-auto rounded-xl border border-black/[0.06] dark:border-white/10">
                        <table class="w-full text-left text-xs">
                          <thead>
                            <tr class="border-b border-black/[0.06] dark:border-white/10 text-gray-400 dark:text-gray-500">
                              <th class="px-3 py-2 font-medium">{{ __('explore.cmp_when') }}</th>
                              <th class="px-3 py-2 font-medium">{{ __('explore.cmp_time') }}</th>
                              <th class="px-3 py-2 font-medium">{{ __('explore.cmp_speed') }}</th>
                              <th class="px-3 py-2 font-medium">{{ __('explore.cmp_calories') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <template x-for="r in routeComparison" :key="r.id">
                              <tr class="border-b border-black/[0.04] dark:border-white/[0.06]"
                                  :class="r.isCurrent ? 'bg-accent/5' : ''">
                                <td class="px-3 py-2">
                                  <span class="text-gray-700 dark:text-gray-300" x-text="r.when ? fmtDateTime(r.when) : r.name"></span>
                                  <span x-show="r.isFastest" class="ml-1 rounded bg-green-100 dark:bg-green-900/40 px-1 py-0.5 text-[10px] font-medium text-green-700 dark:text-green-300">{{ __('explore.cmp_fastest') }}</span>
                                </td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400" x-text="r.durationS ? fmtDuration(r.durationS) : '—'"></td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400" x-text="r.speedMps ? fmtSpeed(r.speedMps) : '—'"></td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400" x-text="r.calories != null ? (r.calories + ' kcal') : '—'"></td>
                              </tr>
                            </template>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </template>

                  {{-- Elevation profile (or graceful no-elevation state) --}}
                  <div class="mt-5">
                    <p class="mb-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('explore.elevation_profile') }}</p>
                    <template x-if="hasElevationProfile">
                      <div x-ref="elevation" class="w-full"></div>
                    </template>
                    <template x-if="! hasElevationProfile">
                      <p class="rounded-lg bg-black/[0.03] dark:bg-white/5 px-3 py-6 text-center text-xs text-gray-500 dark:text-gray-400">{{ __('explore.no_elevation') }}</p>
                    </template>
                  </div>

                  {{-- Free-text note --}}
                  <div class="mt-5">
                    <p class="mb-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">{{ __('explore.note') }}</p>
                    <textarea rows="2" placeholder="{{ __('explore.note_placeholder') }}"
                      :value="selectedTrack.note || ''" @change="saveNote(selectedTrack, $event.target.value)"
                      class="w-full rounded-lg border-0 bg-black/[0.04] dark:bg-white/10 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-2 focus:ring-accent"></textarea>
                  </div>

                  {{-- Coupled photos, chronological + an "add photos" entry point --}}
                  <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between">
                      <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        {{ __('explore.coupled_photos') }}
                        <span class="text-gray-400" x-text="'(' + coupledPhotos.length + ')'"></span>
                      </p>
                      <button type="button" @click="openPhotoPicker(selectedTrackId)"
                        class="inline-flex items-center gap-1 rounded-lg border border-black/[0.08] dark:border-white/10 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 transition hover:border-accent hover:text-accent">
                        <x-icon name="plus" class="h-3.5 w-3.5" />{{ __('explore.add_photos') }}
                      </button>
                    </div>
                    <div class="flex flex-wrap gap-2" x-show="coupledPhotos.length">
                      <template x-for="p in coupledPhotos" :key="p.id">
                        <button type="button"
                             class="h-14 w-14 shrink-0 rounded-lg bg-black/[0.06] dark:bg-white/10 bg-cover bg-center ring-offset-1 ring-offset-white dark:ring-offset-[#1c1c1e] transition hover:opacity-90 focus:outline-none"
                             :class="focusedPhotoId === p.id ? 'ring-2 ring-accent' : ''"
                             :title="(p.name || p.id) + ' — ' + @js(__('explore.show_on_route'))"
                             @click="focusPhoto(p)"
                             :style="thumbs[p.id] ? ('background-image:url(\'' + thumbs[p.id] + '\')') : ''"
                             x-init="$nextTick(() => _thumbFor(p))"></button>
                      </template>
                    </div>
                    <p x-show="! coupledPhotos.length" class="text-xs text-gray-400 dark:text-gray-500">{{ __('explore.no_coupled_photos') }}</p>
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

        {{-- Assign-to-tour modal: search/autocomplete + source filter over tracks --}}
        <template x-teleport="body">
          <div x-show="assignFor" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closeAssign()">
            <div class="absolute inset-0 bg-gray-900/40" @click="closeAssign()"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-md flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('explore.assign_to_track') }}</h3>
                <button type="button" @click="closeAssign()" class="rounded-md p-1.5 text-gray-500 hover:bg-black/[0.04] dark:hover:bg-white/10" :aria-label="@js(__('explore.close'))">
                  <x-icon name="x-mark" class="h-4 w-4" />
                </button>
              </div>
              <div class="space-y-3 px-5 py-4">
                {{-- Autocomplete search --}}
                <input type="search" x-model="assignQuery" x-ref="assignSearch"
                  x-effect="if (assignFor) $nextTick(() => $refs.assignSearch && $refs.assignSearch.focus())"
                  placeholder="{{ __('explore.search_tracks') }}"
                  class="block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm shadow-sm focus:border-accent focus:ring-accent">
                {{-- Source filter chips --}}
                <div class="flex flex-wrap gap-1.5">
                  @foreach (['all','imported','planned','recorded'] as $src)
                    <button type="button" @click="assignSource = '{{ $src }}'"
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="assignSource === '{{ $src }}' ? 'll-accent' : 'bg-black/[0.04] dark:bg-white/10 text-gray-600 dark:text-gray-300 hover:bg-accent/10'">
                      {{ __('explore.filter_' . $src) }}
                    </button>
                  @endforeach
                </div>
              </div>
              {{-- Track list --}}
              <div class="min-h-0 flex-1 overflow-y-auto border-t border-black/[0.06] dark:border-white/10 px-2 py-2">
                <template x-for="t in assignCandidates" :key="t.id">
                  <button type="button" @click="assignToTrack(assignFor, t.id)"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-accent/5"
                    :class="assignFor && couplings[assignFor] && couplings[assignFor].trackId === t.id ? 'bg-accent/10' : ''">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + trackColor(t)"></span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm text-gray-900 dark:text-gray-100" x-text="t.name"></span>
                      <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="fmtDistance(t.stats && t.stats.distanceM)"></span>
                    </span>
                    <x-icon name="check" class="h-4 w-4 shrink-0 text-accent"
                      x-show="assignFor && couplings[assignFor] && couplings[assignFor].trackId === t.id" />
                  </button>
                </template>
                <p x-show="! assignCandidates.length" class="px-3 py-6 text-center text-xs text-gray-500 dark:text-gray-400">{{ __('explore.no_search_results') }}</p>
              </div>
              {{-- Footer: clear coupling --}}
              <div class="flex items-center justify-between border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                <button type="button" x-show="assignFor && couplings[assignFor]" @click="clearCoupling(assignFor); closeAssign()"
                  class="text-xs font-medium text-red-500 hover:text-red-600">{{ __('explore.clear_coupling') }}</button>
                <span x-show="! (assignFor && couplings[assignFor])"></span>
                <button type="button" @click="closeAssign()" class="rounded-md border border-gray-300 dark:border-white/10 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">{{ __('explore.close') }}</button>
              </div>
            </div>
          </div>
        </template>

        {{-- Photo picker (track detail → add photos): choose from ALL photos,
             including ones with no GPS that never show in the map media list. --}}
        <template x-teleport="body">
          <div x-show="photoPickerFor" x-cloak class="fixed inset-0 z-[1100] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.escape.window="closePhotoPicker()">
            <div class="absolute inset-0 bg-gray-900/40" @click="closePhotoPicker()"></div>
            <div class="relative flex max-h-[80vh] w-full max-w-lg flex-col rounded-2xl border border-black/[0.06] dark:border-white/10 bg-white dark:bg-[#1c1c1e] shadow-xl">
              <div class="flex items-center justify-between border-b border-black/[0.06] dark:border-white/10 px-5 py-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('explore.add_photos') }}</h3>
                <button type="button" @click="closePhotoPicker()" class="rounded-md p-1.5 text-gray-500 hover:bg-black/[0.04] dark:hover:bg-white/10" :aria-label="@js(__('explore.close'))">
                  <x-icon name="x-mark" class="h-4 w-4" />
                </button>
              </div>
              <div class="px-5 py-4">
                <input type="search" x-model="photoPickerQuery"
                  placeholder="{{ __('explore.search_photos') }}"
                  class="block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm shadow-sm focus:border-accent focus:ring-accent">
              </div>
              <div class="min-h-0 flex-1 overflow-y-auto border-t border-black/[0.06] dark:border-white/10 p-3">
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                  <template x-for="p in pickerPhotos" :key="p.id">
                    <button type="button" @click="togglePickerPhoto(p.id)"
                      class="relative aspect-square overflow-hidden rounded-lg bg-black/[0.06] dark:bg-white/10 bg-cover bg-center ring-2 ring-transparent transition"
                      :class="pickerCoupled(p.id) ? '!ring-accent' : ''"
                      :title="p.name || p.id"
                      :style="thumbs[p.id] ? ('background-image:url(\'' + thumbs[p.id] + '\')') : ''"
                      x-init="$nextTick(() => _thumbFor(p))">
                      <span x-show="pickerCoupled(p.id)" class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full ll-accent shadow">
                        <x-icon name="check" class="h-3 w-3 text-white" />
                      </span>
                    </button>
                  </template>
                </div>
                <p x-show="! pickerPhotos.length" class="px-3 py-8 text-center text-xs text-gray-500 dark:text-gray-400">{{ __('explore.no_photos') }}</p>
              </div>
              <div class="flex justify-end border-t border-black/[0.06] dark:border-white/10 px-5 py-3">
                <button type="button" @click="closePhotoPicker()" class="rounded-md ll-accent px-4 py-2 text-sm font-medium">{{ __('explore.done') }}</button>
              </div>
            </div>
          </div>
        </template>

      </div>
    </template>

  </div>
</x-layouts.app>
