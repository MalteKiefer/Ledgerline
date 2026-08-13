<template>
  <div v-if="state !== 'empty'" class="mt-1 overflow-hidden rounded-lg border border-[var(--ll-border)]">
    <div v-if="state === 'loading'" class="flex h-[160px] items-center justify-center text-xs text-[var(--ll-muted)]">
      <Icon name="progress_activity" :size="16" class="mr-1 animate-spin" />{{ t('contacts.ui.map_loading') }}
    </div>
    <div v-show="state === 'ready'" ref="mapEl" class="h-[160px] w-full" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick } from 'vue';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { trans as t } from 'laravel-vue-i18n';
import { Icon } from '@spa/ui';
import { useContactsStore } from '@spa/stores/contacts';

// Read-only address mini-map. Geocodes the address on mount via the shared
// server geo proxy and renders a Leaflet tile map. A module-level cache dedupes
// identical addresses across every instance for the whole session, so browsing
// contacts never re-hits the (rate-limited) geo endpoint for a known address.
const props = defineProps<{ text: string }>();

const store = useContactsStore();
type Coord = { lat: number; lon: number } | null;
const geoCache: Map<string, Coord> = ((window as unknown as { __llGeoCache?: Map<string, Coord> }).__llGeoCache ??= new Map());

const state = ref<'loading' | 'ready' | 'empty'>('loading');
const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;

const pinIcon = L.divIcon({
  className: 'll-map-pin',
  html: '<svg viewBox="0 0 24 24" width="28" height="28" fill="#6750a4" stroke="#fff" stroke-width="1"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7z"/><circle cx="12" cy="9" r="2.6" fill="#fff" stroke="none"/></svg>',
  iconSize: [28, 28],
  iconAnchor: [14, 28],
});

function destroy(): void {
  if (map) { map.remove(); map = null; }
}

async function resolve(text: string): Promise<Coord> {
  const key = text.trim().toLowerCase();
  if (geoCache.has(key)) return geoCache.get(key) ?? null;
  try {
    const res = await store.geoSearch(text);
    const hit = res[0] ? { lat: res[0].lat, lon: res[0].lon } : null;
    geoCache.set(key, hit);
    return hit;
  } catch {
    // Do NOT cache a transient failure (e.g. 429) — allow a later retry.
    return null;
  }
}

async function render(): Promise<void> {
  destroy();
  state.value = 'loading';
  const text = props.text.trim();
  if (text.length < 3) { state.value = 'empty'; return; }
  const c = await resolve(text);
  if (!c) { state.value = 'empty'; return; }
  state.value = 'ready';
  await nextTick();
  const el = mapEl.value;
  if (!el) return;
  map = L.map(el, { scrollWheelZoom: false, zoomControl: true, attributionControl: true }).setView([c.lat, c.lon], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
  L.marker([c.lat, c.lon], { icon: pinIcon }).addTo(map);
  setTimeout(() => map?.invalidateSize(), 60);
}

watch(() => props.text, () => { void render(); }, { immediate: true });
onBeforeUnmount(destroy);
</script>

<style>
.ll-map-pin { background: transparent; border: none; }
</style>
