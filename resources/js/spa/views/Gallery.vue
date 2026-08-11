<template>
  <div
    class="relative min-h-[calc(100vh-120px)]"
    @dragenter.prevent="onDragEnter" @dragover.prevent @dragleave.prevent="onDragLeave" @drop.prevent="onDrop"
  >
    <!-- Drag overlay -->
    <div v-show="dragDepth > 0 && !up.active" class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-500/10">
      <div class="rounded-xl bg-[var(--ll-elevated)] px-6 py-4 text-center shadow-lg">
        <Icon name="photo_library" :size="32" class="text-primary-500" />
        <div class="mt-1 text-sm font-medium">{{ t('gallery.drop_here') }}</div>
      </div>
    </div>

    <Card body-class="p-0">
      <!-- Toolbar -->
      <div class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2.5">
        <h2 class="text-sm font-semibold">{{ showTrash ? t('gallery.trash') : t('messages.nav.gallery') }}</h2>
        <div v-if="!showTrash" class="relative ml-3 hidden sm:block">
          <Icon name="search" :size="16" class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[var(--ll-muted)]" />
          <input
            v-model="searchQuery" type="search" :placeholder="t('gallery.search_ph')"
            class="w-52 rounded-lg border border-[var(--ll-border)] bg-transparent py-1.5 pl-8 pr-7 text-sm focus:border-primary-500 focus:outline-none"
            @keyup.enter="doSearch" @search="searchQuery ? undefined : clearSearch()"
          >
          <button v-if="searchActive" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[var(--ll-muted)] hover:text-[var(--ll-fg)]" @click="clearSearch"><Icon name="close" :size="15" /></button>
        </div>
        <div class="ml-auto flex items-center gap-1">
          <template v-if="!showTrash">
            <Btn :variant="viewMode === 'grid' ? 'solid' : 'ghost'" size="sm" icon="grid_view" @click="setView('grid')">{{ t('gallery.view_grid') }}</Btn>
            <Btn :variant="viewMode === 'map' ? 'solid' : 'ghost'" size="sm" icon="map" @click="setView('map')">{{ t('gallery.view_map') }}</Btn>
          </template>
          <Btn variant="solid" size="sm" icon="upload" @click="pick">{{ t('gallery.upload') }}</Btn>
          <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onPick">
          <Btn v-if="showTrash && trashPhotos.length" variant="ghost" size="sm" icon="delete" class="text-red-600" @click="onEmpty">{{ t('gallery.empty_trash') }}</Btn>
          <Btn variant="ghost" size="sm" :icon="showTrash ? 'photo_library' : 'delete'" @click="toggleTrash">{{ showTrash ? t('gallery.back') : t('gallery.trash') }}</Btn>
        </div>
      </div>

      <!-- Album chips -->
      <div v-if="!showTrash && viewMode === 'grid'" class="flex flex-wrap items-center gap-1.5 border-b border-[var(--ll-border)] px-4 py-2">
        <button class="rounded-full px-3 py-1 text-xs font-medium" :class="albumId === null ? 'bg-primary-500 text-white' : 'bg-black/[0.05] dark:bg-white/10'" @click="selectAlbum(null)">{{ t('gallery.all_photos') }}</button>
        <button
          v-for="a in g.albums" :key="a.id"
          class="flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium"
          :class="albumId === a.id ? 'bg-primary-500 text-white' : 'bg-black/[0.05] dark:bg-white/10'"
          @click="selectAlbum(a.id)"
        >
          <span>{{ a.name }}</span>
          <span class="tabular-nums opacity-70">{{ a.count }}</span>
          <Icon v-if="albumId === a.id" name="edit" :size="14" class="opacity-80 hover:opacity-100" @click.stop="renameAlbum(a)" />
          <Icon v-if="albumId === a.id" name="delete" :size="14" class="opacity-80 hover:opacity-100" @click.stop="deleteAlbum(a)" />
        </button>
        <button class="rounded-full border border-dashed border-[var(--ll-border)] px-3 py-1 text-xs text-[var(--ll-muted)] hover:text-[var(--ll-fg)]" @click="newAlbum">+ {{ t('gallery.new_album') }}</button>
      </div>

      <!-- Selection bar -->
      <div v-if="!showTrash && viewMode === 'grid' && selected.size" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-4 py-2 text-sm">
        <span class="font-medium">{{ selected.size }} {{ t('gallery.selected') }}</span>
        <div class="ml-auto flex items-center gap-1">
          <div class="relative">
            <Btn variant="ghost" size="sm" icon="library_add" @click="albumMenu = !albumMenu">{{ t('gallery.add_to_album') }}</Btn>
            <div v-if="albumMenu" class="absolute right-0 z-20 mt-1 w-52 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-elevated)] py-1 shadow-lg">
              <div v-if="!g.albums.length" class="px-3 py-2 text-xs text-[var(--ll-muted)]">{{ t('gallery.no_albums') }}</div>
              <button v-for="a in g.albums" :key="a.id" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-black/[0.05] dark:hover:bg-white/10" @click="addSelectedToAlbum(a)">{{ a.name }}</button>
              <button class="block w-full border-t border-[var(--ll-border)] px-3 py-1.5 text-left text-sm text-primary-600" @click="newAlbumWithSelection">+ {{ t('gallery.new_album') }}</button>
            </div>
          </div>
          <Btn v-if="albumId !== null" variant="ghost" size="sm" icon="playlist_remove" @click="removeSelectedFromAlbum">{{ t('gallery.remove_from_album') }}</Btn>
          <Btn variant="ghost" size="sm" icon="delete" class="text-red-600" @click="bulkTrash">{{ t('common.delete') }}</Btn>
          <Btn variant="ghost" size="sm" icon="close" @click="clearSelection">{{ t('gallery.clear_selection') }}</Btn>
        </div>
      </div>

      <!-- Map view -->
      <div v-show="!showTrash && viewMode === 'map'" class="p-3">
        <div v-if="!mapPhotos.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ t('gallery.no_located') }}</div>
        <div v-show="mapPhotos.length" ref="mapEl" class="h-[calc(100vh-230px)] w-full overflow-hidden rounded-lg border border-[var(--ll-border)]" />
      </div>

      <!-- Grid view -->
      <div v-if="viewMode === 'grid' || showTrash" class="p-3">
        <div v-if="!current.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ showTrash ? t('gallery.trash_empty') : (searchActive ? t('gallery.search_none') : t('gallery.empty')) }}</div>
        <div v-else class="grid grid-cols-3 gap-1.5 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
          <template v-for="(p, i) in current" :key="p.id">
            <div v-if="!showTrash && p._dayHeader" class="col-span-full px-0.5 pt-2 text-xs font-semibold text-[var(--ll-muted)]">{{ p._dayHeader }}</div>
            <div
              class="group relative aspect-square overflow-hidden rounded-lg bg-black/[0.04] dark:bg-white/5"
              :class="selected.has(p.id) ? 'ring-2 ring-primary-500 ring-offset-1 ring-offset-[var(--ll-surface)]' : ''"
              @mouseenter="p.motion && !showTrash ? hoverId = p.id : null"
              @mouseleave="hoverId === p.id ? hoverId = -1 : null"
            >
              <!-- Media: processing/failed placeholder → thumbnail → pending spinner -->
              <div v-if="!showTrash && p.status === 'processing'" class="flex h-full w-full flex-col items-center justify-center gap-1 px-1 text-center text-[10px] text-[var(--ll-muted)]">
                <Icon name="movie" :size="22" class="opacity-50" />
                <Icon name="progress_activity" :size="14" class="animate-spin opacity-60" />
                <span>{{ t('gallery.processing') }}</span>
              </div>
              <div v-else-if="!showTrash && p.status === 'failed'" class="flex h-full w-full flex-col items-center justify-center gap-1 px-1 text-center text-[10px] text-red-500">
                <Icon name="error_outline" :size="22" class="opacity-70" />
                <span>{{ t('gallery.failed') }}</span>
              </div>
              <img
                v-else-if="p.thumb"
                :src="g.thumbUrl(p.id)" loading="lazy"
                class="h-full w-full cursor-pointer object-cover"
                :class="selected.has(p.id) ? 'opacity-80' : ''"
                @click="showTrash ? undefined : onTileClick($event, i, p)"
                @error="onThumbError"
              >
              <button
                v-else
                class="flex h-full w-full items-center justify-center text-[var(--ll-muted)]"
                :title="t('gallery.thumb_pending')"
                @click="showTrash ? undefined : onTileClick($event, i, p)"
              >
                <Icon name="progress_activity" :size="22" class="animate-spin opacity-60" />
              </button>
              <!-- Independent overlays -->
              <video
                v-if="p.motion && hoverId === p.id"
                :src="g.motionUrl(p.id)" muted loop autoplay playsinline
                class="pointer-events-none absolute inset-0 h-full w-full object-cover"
              />
              <span v-if="p.media_type === 'video' && p.status === 'ready' && p.thumb && !showTrash" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <Icon name="play_circle" :size="34" class="text-white/90 drop-shadow" />
              </span>
              <span v-if="p.media_type === 'video' && p.duration && !showTrash" class="pointer-events-none absolute bottom-1 right-1 rounded bg-black/50 px-1 py-0.5 text-[9px] font-semibold text-white">{{ fmtDuration(p.duration) }}</span>
              <span v-if="p.motion && !showTrash" class="pointer-events-none absolute bottom-1 right-1 flex items-center gap-0.5 rounded bg-black/50 px-1 py-0.5 text-[9px] font-semibold uppercase text-white">
                <Icon name="motion_photos_on" :size="11" /> Live
              </span>
              <button
                v-if="!showTrash"
                class="absolute left-1 top-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white/80 shadow transition"
                :class="selected.has(p.id) ? 'bg-primary-500' : 'bg-black/30 opacity-0 group-hover:opacity-100'"
                @click.stop="toggleAt(i, p)"
              >
                <Icon v-if="selected.has(p.id)" name="check" :size="12" class="text-white" />
              </button>
              <Icon v-if="p.lat !== null && !showTrash" name="location_on" :size="14" class="absolute bottom-1 left-1 text-white drop-shadow" />
              <Icon v-if="p.favorite && !showTrash" name="star" :size="16" class="absolute right-1 top-1 text-amber-400 drop-shadow" />
              <div v-if="showTrash" class="absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-black/40 p-1">
                <button class="rounded p-1 text-white hover:bg-white/20" :title="t('common.restore')" @click="onRestore(p.id)"><Icon name="restore" :size="16" /></button>
                <button class="rounded p-1 text-white hover:bg-white/20" :title="t('gallery.delete_forever')" @click="onForce(p.id)"><Icon name="delete_forever" :size="16" /></button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </Card>

    <!-- Upload progress -->
    <Teleport to="body">
      <div v-show="up.active" class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/30">
        <div class="w-80 max-w-[90%] rounded-xl bg-[var(--ll-elevated)] px-6 py-5 shadow-xl">
          <div class="flex items-center gap-2 text-sm font-medium">
            <Icon name="upload" :size="20" class="text-primary-500" />
            {{ t('gallery.uploading') }} <span class="ml-auto tabular-nums text-[var(--ll-muted)]">{{ up.done }} / {{ up.total }}</span>
          </div>
          <div class="mt-1 truncate text-xs text-[var(--ll-muted)]">{{ up.name }}</div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/[0.08] dark:bg-white/10">
            <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: upPct + '%' }" />
          </div>
          <div class="mt-1 text-right text-xs tabular-nums text-[var(--ll-muted)]">{{ upPct }}%</div>
        </div>
      </div>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
      <div v-if="viewer >= 0" class="fixed inset-0 z-[2100] flex items-center justify-center bg-black/90" @click.self="viewer = -1">
        <button class="absolute right-4 top-4 rounded-full p-2 text-white/80 hover:bg-white/10" @click="viewer = -1"><Icon name="close" :size="24" /></button>
        <button class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="step(-1)"><Icon name="chevron_left" :size="32" /></button>
        <button class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="step(1)"><Icon name="chevron_right" :size="32" /></button>
        <video v-if="viewerPhoto && viewerPhoto.media_type === 'video'" :src="g.playUrl(viewerPhoto.id)" autoplay controls playsinline class="max-h-[92vh] max-w-[92vw] object-contain" />
        <video v-else-if="viewerPhoto && motionPlaying && viewerPhoto.motion" :src="g.motionUrl(viewerPhoto.id)" autoplay loop controls playsinline class="max-h-[92vh] max-w-[92vw] object-contain" />
        <img v-else-if="viewerPhoto" :src="g.rawUrl(viewerPhoto.id)" class="max-h-[92vh] max-w-[92vw] object-contain transition-transform" :style="transformStyle(viewerPhoto)">
        <div class="absolute inset-x-0 bottom-0 flex items-center gap-3 bg-gradient-to-t from-black/70 to-transparent px-6 py-4 text-sm text-white">
          <div class="min-w-0 flex-1">
            <div class="truncate">{{ viewerPhoto?.name }}</div>
            <div class="truncate text-xs text-white/70">
              <span v-if="viewerDate">{{ viewerDate }}</span>
              <span v-if="viewerPhoto?.place"> · {{ viewerPhoto?.place }}</span>
              <span v-else-if="viewerPhoto?.camera"> · {{ viewerPhoto?.camera }}</span>
            </div>
          </div>
          <button v-if="viewerPhoto?.motion" class="rounded-full p-2 hover:bg-white/10" :class="motionPlaying ? 'text-primary-300' : ''" :title="t('gallery.play_motion')" @click="motionPlaying = !motionPlaying">
            <Icon name="motion_photos_on" :size="22" />
          </button>
          <button class="rounded-full p-2 hover:bg-white/10" :title="t('gallery.favorite')" @click="onFav">
            <Icon :name="viewerPhoto?.favorite ? 'star' : 'star_border'" :size="22" :class="viewerPhoto?.favorite ? 'text-amber-400' : ''" />
          </button>
          <button class="rounded-full p-2 hover:bg-white/10" :title="t('gallery.edit')" @click="openEdit"><Icon name="edit" :size="22" /></button>
          <!-- download original/edited -->
          <div class="relative">
            <button class="rounded-full p-2 hover:bg-white/10" :title="t('common.download')" @click="dlMenu = !dlMenu"><Icon name="download" :size="22" /></button>
            <div v-if="dlMenu" class="absolute bottom-full right-0 mb-1 w-44 rounded-lg border border-white/10 bg-neutral-900 py-1 text-white shadow-lg">
              <a :href="g.downloadUrl(viewerPhoto?.id ?? 0, 'original')" download class="block px-3 py-1.5 text-sm hover:bg-white/10" @click="dlMenu = false">{{ t('gallery.dl_original') }}</a>
              <a v-if="isEdited(viewerPhoto)" :href="g.downloadUrl(viewerPhoto?.id ?? 0, 'edited')" download class="block px-3 py-1.5 text-sm hover:bg-white/10" @click="dlMenu = false">{{ t('gallery.dl_edited') }}</a>
            </div>
          </div>
          <button class="rounded-full p-2 text-red-400 hover:bg-white/10" :title="t('common.delete')" @click="onDelete"><Icon name="delete" :size="22" /></button>
        </div>
      </div>
    </Teleport>

    <!-- Edit modal -->
    <Teleport to="body">
      <div v-if="edit.open" class="fixed inset-0 z-[2200] flex items-center justify-center bg-black/50 p-4" @click.self="edit.open = false">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-[var(--ll-elevated)] shadow-xl">
          <div class="flex items-center justify-between border-b border-[var(--ll-border)] px-5 py-3">
            <h3 class="text-sm font-semibold">{{ t('gallery.edit') }}</h3>
            <button class="rounded-full p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10" @click="edit.open = false"><Icon name="close" :size="18" /></button>
          </div>
          <div class="grid gap-4 p-5 sm:grid-cols-2">
            <!-- Live preview + rotate/mirror -->
            <div>
              <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-black/[0.06] dark:bg-white/5">
                <img v-if="edit.id" :src="g.rawUrl(edit.id)" class="max-h-full max-w-full object-contain transition-transform" :style="previewStyle">
              </div>
              <div class="mt-2 flex justify-center gap-1">
                <Btn variant="ghost" size="sm" icon="rotate_left" @click="rotate(-90)">{{ t('gallery.rotate_left') }}</Btn>
                <Btn variant="ghost" size="sm" icon="rotate_right" @click="rotate(90)">{{ t('gallery.rotate_right') }}</Btn>
                <Btn :variant="edit.flip_h ? 'solid' : 'ghost'" size="sm" icon="flip" @click="edit.flip_h = !edit.flip_h">{{ t('gallery.mirror') }}</Btn>
              </div>
            </div>
            <!-- Metadata -->
            <div class="space-y-3">
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.date') }}</span>
                <input v-model="edit.date" type="date" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm">
              </label>
              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.time') }}</span>
                <input v-model="edit.time" type="time" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm">
              </label>
              <LocationField
                :model-value="edit.place" :lat="edit.lat" :lon="edit.lng"
                :label="t('gallery.location')"
                @update:model-value="edit.place = $event"
                @update:lat="edit.lat = $event"
                @update:lon="edit.lng = $event"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-[var(--ll-border)] px-5 py-3">
            <Btn variant="ghost" size="sm" @click="edit.open = false">{{ t('common.cancel') }}</Btn>
            <Btn variant="solid" size="sm" :loading="edit.saving" @click="saveEdit">{{ t('common.save') }}</Btn>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Icon } from '@spa/ui';
import LocationField from '@spa/components/LocationField.vue';
import { useGalleryStore, type Photo, type Album } from '@spa/stores/gallery';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';

type Row = Photo & { _dayHeader?: string };

const g = useGalleryStore();
const { success, error } = useToast();

const fileInput = ref<HTMLInputElement | null>(null);
const showTrash = ref(false);
const viewMode = ref<'grid' | 'map'>('grid');
const trashPhotos = ref<Photo[]>([]);
const albumId = ref<number | null>(null);
const albumMenu = ref(false);
const dlMenu = ref(false);

const selected = ref<Set<number>>(new Set());
let anchor = -1;
const hoverId = ref(-1);
const motionPlaying = ref(false);

const searchQuery = ref('');
const searchActive = ref(false);
const searchResults = ref<Photo[]>([]);

const current = computed<Row[]>(() => {
  if (showTrash.value) return trashPhotos.value as Row[];
  const src = searchActive.value ? searchResults.value : g.photos;
  let last = '';
  return src.map((p): Row => {
    const day = dayLabel(p.taken_at ?? p.created_at);
    const header = day !== last ? day : undefined;
    last = day;
    return { ...p, _dayHeader: header };
  });
});
const mapPhotos = computed(() => g.photos.filter((p) => p.lat !== null && p.lng !== null));
const viewer = ref(-1);
const viewerPhoto = computed(() => (viewer.value >= 0 ? current.value[viewer.value] ?? null : null));
const viewerDate = computed(() => { const p = viewerPhoto.value; return p ? fullDate(p.taken_at ?? p.created_at) : ''; });

const up = reactive({ active: false, done: 0, total: 0, name: '', frac: 0 });
const upPct = computed(() => (up.total ? Math.min(100, Math.round(((up.done + up.frac) / up.total) * 100)) : 0));
const dragDepth = ref(0);

const edit = reactive({ open: false, saving: false, id: 0, version: 0, date: '', time: '', place: '' as string, lat: null as number | null, lng: null as number | null, rotation: 0, flip_h: false });

let thumbPoll: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
  void g.load(); void g.loadAlbums();
  window.addEventListener('keydown', onKey); window.addEventListener('focus', onFocus);
  // Thumbnails are generated by a worker after upload; while any are still
  // pending, poll so the grid swaps the spinner for the image once ready.
  thumbPoll = setInterval(() => {
    if (!showTrash.value && !searchActive.value && !up.active && !edit.open && g.photos.some((p) => !p.thumb || p.status === 'processing')) void refresh();
  }, 4000);
});
onUnmounted(() => {
  window.removeEventListener('keydown', onKey); window.removeEventListener('focus', onFocus);
  if (thumbPoll) clearInterval(thumbPoll);
  destroyMap();
});
function onFocus() { if (!document.hidden && !up.active && !showTrash.value && !searchActive.value) void g.load(albumId.value ?? undefined); }

function dayLabel(iso: string | null): string {
  if (!iso) return '';
  try { return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }); } catch { return ''; }
}
function fullDate(iso: string | null): string {
  if (!iso) return '';
  try { return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch { return ''; }
}
function fmtDuration(sec: number): string {
  const s = Math.max(0, Math.round(sec));
  const m = Math.floor(s / 60);
  return `${m}:${String(s % 60).padStart(2, '0')}`;
}
function isEdited(p: Photo | null): boolean { return !!p && (p.rotation !== 0 || p.flip_h); }
function transformStyle(p: Photo) { return { transform: `rotate(${p.rotation}deg) scaleX(${p.flip_h ? -1 : 1})` }; }
const previewStyle = computed(() => ({ transform: `rotate(${edit.rotation}deg) scaleX(${edit.flip_h ? -1 : 1})` }));

function pick() { fileInput.value?.click(); }
function onPick(e: Event) { const l = (e.target as HTMLInputElement).files; if (l) void uploadList(l); (e.target as HTMLInputElement).value = ''; }
function onThumbError(e: Event) { (e.target as HTMLImageElement).style.visibility = 'hidden'; }

function hasFiles(e: DragEvent) { return Array.from(e.dataTransfer?.types ?? []).includes('Files'); }
function onDragEnter(e: DragEvent) { if (hasFiles(e)) dragDepth.value++; }
function onDragLeave(e: DragEvent) { if (hasFiles(e)) dragDepth.value = Math.max(0, dragDepth.value - 1); }
function onDrop(e: DragEvent) { dragDepth.value = 0; const l = e.dataTransfer?.files; if (l && l.length) void uploadList(l); }

function baseName(name: string): string { return name.replace(/\.[^.]+$/, '').toLowerCase(); }
function isMotionFile(f: File): boolean { return f.type.startsWith('video/') || /\.(mov|mp4|m4v|qt)$/i.test(f.name); }

async function uploadList(list: FileList) {
  const all = Array.from(list);
  const images = all.filter((f) => f.type.startsWith('image/'));
  const motions = all.filter(isMotionFile);
  if (!images.length && !motions.length) return;
  Object.assign(up, { active: true, done: 0, total: images.length + motions.length, name: '', frac: 0 });
  // Pair a Live Photo's .MOV to its still by base name (IMG_1234.HEIC ↔ IMG_1234.MOV).
  // Seed with the existing library so a clip uploaded in a later batch still merges.
  const idByBase = new Map<string, number>();
  for (const p of g.photos) idByBase.set(baseName(p.name), p.id);
  let dupes = 0;
  try {
    for (const f of images) {
      up.name = f.name; up.frac = 0;
      const r = await g.upload(f, (fr) => { up.frac = fr; });
      if (r.duplicate) dupes++;
      idByBase.set(baseName(f.name), r.photo.id);
      up.frac = 0; up.done++;
    }
    for (const f of motions) {
      up.name = f.name; up.frac = 0;
      const id = idByBase.get(baseName(f.name));
      if (id !== undefined) {
        // Video paired to a just-uploaded/existing still → Apple Live Photo clip.
        await g.attachMotion(id, f, (fr) => { up.frac = fr; });
      } else {
        // Standalone video → upload as its own entry (processed on the worker).
        const r = await g.upload(f, (fr) => { up.frac = fr; });
        if (r.duplicate) dupes++;
      }
      up.frac = 0; up.done++;
    }
    await refresh();
    await g.loadAlbums();
    success(t('common.saved'));
    if (dupes > 0) success(t('gallery.dupes_skipped', { n: String(dupes) }));
  } catch { error(t('common.error')); } finally { up.active = false; }
}
function refresh() { return g.load(albumId.value ?? undefined); }
function setView(m: 'grid' | 'map') { viewMode.value = m; if (m === 'map') void nextTick().then(syncMap); }

// ---- Semantic search (CLIP) ----
async function doSearch() {
  const q = searchQuery.value.trim();
  if (!q) { clearSearch(); return; }
  clearSelection();
  try { searchResults.value = await g.search(q); searchActive.value = true; }
  catch { error(t('common.error')); }
}
function clearSearch() { searchActive.value = false; searchResults.value = []; searchQuery.value = ''; }

// ---- Multi-select ----
function selectAlbum(id: number | null) { albumId.value = id; clearSelection(); clearSearch(); void refresh(); }
function clearSelection() { selected.value = new Set(); anchor = -1; albumMenu.value = false; }
function toggle(id: number) { const s = new Set(selected.value); if (s.has(id)) s.delete(id); else s.add(id); selected.value = s; }
function toggleAt(i: number, p: Row) { toggle(p.id); anchor = i; }
function selectRange(a: number, b: number) {
  const [lo, hi] = a < b ? [a, b] : [b, a];
  const s = new Set(selected.value);
  for (let i = lo; i <= hi; i++) { const p = current.value[i]; if (p) s.add(p.id); }
  selected.value = s;
}
function onTileClick(e: MouseEvent, i: number, p: Row) {
  if (e.shiftKey && anchor >= 0) { selectRange(anchor, i); return; }
  if (e.ctrlKey || e.metaKey) { toggle(p.id); anchor = i; return; }
  if (selected.value.size > 0) { toggle(p.id); anchor = i; return; }
  openViewer(i);
}
async function bulkTrash() {
  const ids = [...selected.value];
  if (!ids.length || !await confirmAsk(t('gallery.bulk_delete_confirm', { n: String(ids.length) }), { danger: true })) return;
  try { await g.bulkDestroy(ids); clearSelection(); await refresh(); await g.loadAlbums(); success(t('common.saved')); } catch { error(t('common.error')); }
}

// ---- Albums ----
async function newAlbum() {
  const name = (await promptAsk(t('gallery.album_name')))?.trim();
  if (!name) return;
  try { await g.createAlbum(name); await g.loadAlbums(); } catch { error(t('common.error')); }
}
async function newAlbumWithSelection() {
  const name = (await promptAsk(t('gallery.album_name')))?.trim();
  if (!name) return;
  try { const r = await g.createAlbum(name); await g.addToAlbum(r.album.id, [...selected.value]); await g.loadAlbums(); clearSelection(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function renameAlbum(a: Album) {
  const name = (await promptAsk(t('gallery.album_name'), { value: a.name }))?.trim();
  if (!name || name === a.name) return;
  try { await g.renameAlbum(a.id, name); await g.loadAlbums(); } catch { error(t('common.error')); }
}
async function deleteAlbum(a: Album) {
  if (!await confirmAsk(t('gallery.delete_album_confirm'), { danger: true })) return;
  try { await g.deleteAlbum(a.id); if (albumId.value === a.id) selectAlbum(null); await g.loadAlbums(); } catch { error(t('common.error')); }
}
async function addSelectedToAlbum(a: Album) {
  try { await g.addToAlbum(a.id, [...selected.value]); await g.loadAlbums(); clearSelection(); success(t('common.saved')); } catch { error(t('common.error')); }
}
async function removeSelectedFromAlbum() {
  if (albumId.value === null) return;
  try { await g.removeFromAlbum(albumId.value, [...selected.value]); clearSelection(); await refresh(); await g.loadAlbums(); } catch { error(t('common.error')); }
}

// ---- Lightbox ----
function openViewer(i: number) { viewer.value = i; dlMenu.value = false; motionPlaying.value = false; }
function step(d: number) {
  if (viewer.value < 0) return;
  const n = current.value.length;
  if (!n) { viewer.value = -1; return; }
  viewer.value = (viewer.value + d + n) % n;
  dlMenu.value = false; motionPlaying.value = false;
}
function onKey(e: KeyboardEvent) {
  if (edit.open) return;
  if (viewer.value < 0) return;
  if (e.key === 'Escape') viewer.value = -1;
  else if (e.key === 'ArrowLeft') step(-1);
  else if (e.key === 'ArrowRight') step(1);
}
async function onFav() {
  const p = viewerPhoto.value; if (!p) return;
  const next = !p.favorite;
  try { await g.favorite(p.id, next); p.favorite = next; } catch { error(t('common.error')); }
}
async function onDelete() {
  const p = viewerPhoto.value; if (!p) return;
  if (!await confirmAsk(t('gallery.delete_confirm'), { danger: true })) return;
  try { await g.destroy(p.id); viewer.value = -1; await refresh(); await g.loadAlbums(); success(t('common.saved')); } catch { error(t('common.error')); }
}

// ---- Edit ----
function openEdit() {
  const p = viewerPhoto.value; if (!p) return;
  const iso = p.taken_at ?? '';
  Object.assign(edit, {
    open: true, saving: false, id: p.id, version: p.version,
    date: iso ? iso.slice(0, 10) : '', time: iso ? iso.slice(11, 16) : '',
    place: p.place ?? '', lat: p.lat, lng: p.lng, rotation: p.rotation, flip_h: p.flip_h,
  });
  dlMenu.value = false;
}
function rotate(delta: number) { edit.rotation = (((edit.rotation + delta) % 360) + 360) % 360; }
async function saveEdit() {
  edit.saving = true;
  const takenAt = edit.date ? `${edit.date} ${edit.time || '00:00'}:00` : null;
  try {
    const r = await g.update(edit.id, {
      taken_at: takenAt, place: edit.place || null, lat: edit.lat, lng: edit.lng,
      rotation: edit.rotation, flip_h: edit.flip_h, version: edit.version,
    });
    // patch the in-memory photo so grid/lightbox reflect it immediately
    const idx = g.photos.findIndex((x) => x.id === edit.id);
    if (idx >= 0) g.photos[idx] = r.photo;
    edit.open = false;
    success(t('common.saved'));
    await refresh();
    if (viewMode.value === 'map') void nextTick().then(syncMap);
  } catch (e: unknown) {
    error((e as { status?: number })?.status === 409 ? t('gallery.edit_conflict') : t('common.error'));
    await refresh();
  } finally { edit.saving = false; }
}

// ---- Trash ----
async function toggleTrash() {
  showTrash.value = !showTrash.value;
  viewer.value = -1; clearSelection();
  if (showTrash.value) { try { trashPhotos.value = await g.trash(); } catch { error(t('common.error')); } }
  else await refresh();
}
async function onRestore(id: number) { try { await g.restore(id); trashPhotos.value = await g.trash(); await refresh(); } catch { error(t('common.error')); } }
async function onForce(id: number) {
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  try { await g.forceDelete(id); trashPhotos.value = await g.trash(); } catch { error(t('common.error')); }
}
async function onEmpty() {
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  try { await g.emptyTrash(); trashPhotos.value = []; } catch { error(t('common.error')); }
}

// ---- Map ----
const mapEl = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let markers: L.LayerGroup | null = null;
function destroyMap() { if (map) { map.remove(); map = null; markers = null; } }
function syncMap() {
  const pts = mapPhotos.value;
  if (viewMode.value !== 'map' || !pts.length || !mapEl.value) return;
  if (!map) {
    map = L.map(mapEl.value, { attributionControl: true, scrollWheelZoom: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    markers = L.layerGroup().addTo(map);
  }
  markers?.clearLayers();
  const bounds: L.LatLngExpression[] = [];
  for (const p of pts) {
    const ll: L.LatLngExpression = [p.lat as number, p.lng as number];
    bounds.push(ll);
    const inner = p.thumb
      ? `<img src="${g.thumbUrl(p.id)}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)">`
      : '<div style="width:28px;height:28px;border-radius:50%;background:#6750a4;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)"></div>';
    const icon = L.divIcon({ className: 'll-gallery-pin', html: inner, iconSize: [44, 44], iconAnchor: [22, 22] });
    const idx = g.photos.findIndex((x) => x.id === p.id);
    L.marker(ll, { icon }).addTo(markers as L.LayerGroup).on('click', () => openViewer(idx));
  }
  if (bounds.length === 1) map.setView(bounds[0], 15);
  else map.fitBounds(bounds as L.LatLngBoundsExpression, { padding: [40, 40] });
  setTimeout(() => map?.invalidateSize(), 60);
}
watch(() => g.photos, () => { if (viewMode.value === 'map') void nextTick().then(syncMap); });
</script>

<style>
.ll-gallery-pin { background: transparent; border: none; }
</style>
