<template>
  <div class="space-y-2">
    <!-- Text input + address autocomplete -->
    <label class="block">
      <span v-if="label" class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ label }}</span>
      <span class="relative block">
        <span class="relative flex items-center">
          <Icon name="location_on" :size="18" class="absolute left-3 text-[var(--ll-muted)]" />
          <input
            :value="modelValue"
            type="text"
            :placeholder="t('calendar.ui.location_search')"
            autocomplete="off"
            class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent py-2 pl-10 pr-9 text-sm text-[var(--ll-fg)] placeholder:text-[var(--ll-muted)] transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            @input="onInput(($event.target as HTMLInputElement).value)"
            @focus="open = true"
            @blur="onBlur"
            @keydown.escape="open = false"
          >
          <button
            v-if="modelValue || hasCoords"
            type="button"
            class="absolute right-2 grid h-6 w-6 place-items-center rounded text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10"
            :title="t('calendar.ui.location_clear')"
            @mousedown.prevent="clear"
          >
            <Icon name="close" :size="16" />
          </button>
        </span>

        <!-- Suggestions dropdown -->
        <div
          v-if="open && (searching || results.length || searched)"
          class="absolute left-0 right-0 top-full z-[1650] mt-1 overflow-hidden rounded-lg border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-lg"
        >
          <div v-if="searching" class="flex items-center gap-2 px-3 py-2 text-sm text-[var(--ll-muted)]">
            <Icon name="progress_activity" :size="16" class="animate-spin" />
            {{ t('calendar.ui.location_searching') }}
          </div>
          <ul v-else-if="results.length" class="max-h-56 overflow-y-auto py-1">
            <li v-for="(r, i) in results" :key="i">
              <button
                type="button"
                class="flex w-full items-start gap-2 px-3 py-2 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
                @mousedown.prevent="pick(r)"
              >
                <Icon name="location_on" :size="16" class="mt-0.5 shrink-0 text-[var(--ll-muted)]" />
                <span class="min-w-0 flex-1 break-words">{{ r.display }}</span>
              </button>
            </li>
          </ul>
          <div v-else class="px-3 py-2 text-sm text-[var(--ll-muted)]">{{ t('calendar.ui.location_no_results') }}</div>
        </div>
      </span>
    </label>

    <!-- Mini-map (only when coordinates are set) -->
    <div v-if="hasCoords" class="overflow-hidden rounded-lg border border-[var(--ll-border)]">
      <div ref="mapEl" class="h-[180px] w-full" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { trans as t } from 'laravel-vue-i18n';
import { Icon } from '@spa/ui';
import { useCalendarStore, type GeoResult } from '@spa/stores/calendar';

const props = defineProps<{
  modelValue: string;
  lat: number | string | null;
  lon: number | string | null;
  label?: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [string];
  'update:lat': [number | null];
  'update:lon': [number | null];
}>();

const store = useCalendarStore();

// --- autocomplete -----------------------------------------------------------
const open = ref(false);
const searching = ref(false);
const searched = ref(false); // a query has completed (drives the "no matches" line)
const results = ref<GeoResult[]>([]);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
let queryToken = 0; // guards against out-of-order responses

function onInput(value: string): void {
  emit('update:modelValue', value);
  open.value = true;
  if (debounceTimer) clearTimeout(debounceTimer);
  const q = value.trim();
  if (q.length < 2) {
    results.value = [];
    searching.value = false;
    searched.value = false;
    return;
  }
  searching.value = true;
  debounceTimer = setTimeout(() => runSearch(q), 350);
}

async function runSearch(q: string): Promise<void> {
  const token = ++queryToken;
  try {
    const found = await store.geoSearch(q);
    if (token !== queryToken) return; // a newer query superseded this one
    results.value = found;
  } catch {
    if (token !== queryToken) return;
    results.value = []; // endpoint failed → no dropdown, free typing still works
  } finally {
    if (token === queryToken) {
      searching.value = false;
      searched.value = true;
    }
  }
}

function pick(r: GeoResult): void {
  emit('update:modelValue', r.display);
  emit('update:lat', r.lat);
  emit('update:lon', r.lon);
  open.value = false;
  results.value = [];
  searched.value = false;
}

function onBlur(): void {
  // Delay so a click on a suggestion (mousedown) still registers.
  setTimeout(() => { open.value = false; }, 120);
}

function clear(): void {
  emit('update:modelValue', '');
  emit('update:lat', null);
  emit('update:lon', null);
  results.value = [];
  searched.value = false;
  open.value = false;
}

// --- mini-map ---------------------------------------------------------------
const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let marker: L.Marker | null = null;

function toNum(v: number | string | null): number | null {
  if (v === null || v === '') return null;
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? n : null;
}
const latNum = computed(() => toNum(props.lat));
const lonNum = computed(() => toNum(props.lon));
const hasCoords = computed(() => latNum.value !== null && lonNum.value !== null);

// A lightweight SVG pin as a divIcon — no external image asset (Leaflet's
// default marker icons load PNGs that break under bundlers / offline).
const pinIcon = L.divIcon({
  className: 'll-map-pin',
  html: '<svg viewBox="0 0 24 24" width="28" height="28" fill="#6750a4" stroke="#fff" stroke-width="1"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7z"/><circle cx="12" cy="9" r="2.6" fill="#fff" stroke="none"/></svg>',
  iconSize: [28, 28],
  iconAnchor: [14, 28],
});

function destroyMap(): void {
  if (map) {
    map.remove(); // frees the Leaflet instance + clears the container's _leaflet_id
    map = null;
    marker = null;
  }
}

async function syncMap(): Promise<void> {
  if (!hasCoords.value) { destroyMap(); return; }
  await nextTick(); // ensure the v-if container is in the DOM
  const el = mapEl.value;
  if (!el) return;
  const center: L.LatLngExpression = [latNum.value as number, lonNum.value as number];
  if (!map) {
    map = L.map(el, { attributionControl: true, zoomControl: true, scrollWheelZoom: false }).setView(center, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap',
    }).addTo(map);
    marker = L.marker(center, { icon: pinIcon }).addTo(map);
    // The modal animates in; recompute tile layout once it has settled.
    setTimeout(() => map?.invalidateSize(), 60);
  } else {
    map.setView(center, map.getZoom() || 15);
    if (marker) marker.setLatLng(center);
    else marker = L.marker(center, { icon: pinIcon }).addTo(map);
  }
}

watch([hasCoords, latNum, lonNum], () => { void syncMap(); }, { immediate: true });

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer);
  destroyMap();
});
</script>

<style>
/* Reset Leaflet's default divIcon chrome so only the SVG pin shows. */
.ll-map-pin {
  background: transparent;
  border: none;
}
</style>
