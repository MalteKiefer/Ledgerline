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
        <div class="ml-auto flex items-center gap-1">
          <Btn variant="solid" size="sm" icon="upload" @click="pick">{{ t('gallery.upload') }}</Btn>
          <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onPick">
          <Btn v-if="showTrash && trashPhotos.length" variant="ghost" size="sm" icon="delete" class="text-red-600" @click="onEmpty">{{ t('gallery.empty_trash') }}</Btn>
          <Btn variant="ghost" size="sm" :icon="showTrash ? 'photo_library' : 'delete'" @click="toggleTrash">{{ showTrash ? t('gallery.back') : t('gallery.trash') }}</Btn>
        </div>
      </div>

      <!-- Album chips -->
      <div v-if="!showTrash" class="flex flex-wrap items-center gap-1.5 border-b border-[var(--ll-border)] px-4 py-2">
        <button class="rounded-full px-3 py-1 text-xs font-medium" :class="albumId === null ? 'bg-primary-500 text-white' : 'bg-black/[0.05] dark:bg-white/10'" @click="selectAlbum(null)">{{ t('gallery.all_photos') }}</button>
        <button
          v-for="a in g.albums" :key="a.id"
          class="group/album flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium"
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
      <div v-if="!showTrash && selected.size" class="flex items-center gap-2 border-b border-[var(--ll-border)] bg-primary-500/5 px-4 py-2 text-sm">
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

      <div class="p-3">
        <div v-if="!current.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ showTrash ? t('gallery.trash_empty') : t('gallery.empty') }}</div>
        <div v-else class="grid grid-cols-3 gap-1.5 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
          <template v-for="(p, i) in current" :key="p.id">
            <div v-if="!showTrash && p._dayHeader" class="col-span-full px-0.5 pt-2 text-xs font-semibold text-[var(--ll-muted)]">{{ p._dayHeader }}</div>
            <div
              class="group relative aspect-square overflow-hidden rounded-lg bg-black/[0.04] dark:bg-white/5"
              :class="selected.has(p.id) ? 'ring-2 ring-primary-500 ring-offset-1 ring-offset-[var(--ll-surface)]' : ''"
            >
              <img
                :src="g.thumbUrl(p.id)" loading="lazy"
                class="h-full w-full cursor-pointer object-cover"
                :class="selected.has(p.id) ? 'opacity-80' : ''"
                @click="showTrash ? undefined : onTileClick($event, i, p)"
                @error="onThumbError"
              >
              <!-- selection checkbox -->
              <button
                v-if="!showTrash"
                class="absolute left-1 top-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white/80 shadow transition"
                :class="selected.has(p.id) ? 'bg-primary-500' : 'bg-black/30 opacity-0 group-hover:opacity-100'"
                @click.stop="toggleAt(i, p)"
              >
                <Icon v-if="selected.has(p.id)" name="check" :size="12" class="text-white" />
              </button>
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
        <img v-if="viewerPhoto" :src="g.rawUrl(viewerPhoto.id)" class="max-h-[92vh] max-w-[92vw] object-contain">
        <div class="absolute inset-x-0 bottom-0 flex items-center gap-3 bg-gradient-to-t from-black/70 to-transparent px-6 py-4 text-sm text-white">
          <div class="min-w-0 flex-1">
            <div class="truncate">{{ viewerPhoto?.name }}</div>
            <div class="truncate text-xs text-white/70">
              <span v-if="viewerDate">{{ viewerDate }}</span>
              <span v-if="viewerPhoto?.camera"> · {{ viewerPhoto?.camera }}</span>
            </div>
          </div>
          <button class="rounded-full p-2 hover:bg-white/10" :title="t('gallery.favorite')" @click="onFav">
            <Icon :name="viewerPhoto?.favorite ? 'star' : 'star_border'" :size="22" :class="viewerPhoto?.favorite ? 'text-amber-400' : ''" />
          </button>
          <a :href="g.rawUrl(viewerPhoto?.id ?? 0)" download class="rounded-full p-2 hover:bg-white/10" :title="t('common.download')"><Icon name="download" :size="22" /></a>
          <button class="rounded-full p-2 text-red-400 hover:bg-white/10" :title="t('common.delete')" @click="onDelete"><Icon name="delete" :size="22" /></button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Icon } from '@spa/ui';
import { useGalleryStore, type Photo, type Album } from '@spa/stores/gallery';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';

type Row = Photo & { _dayHeader?: string };

const g = useGalleryStore();
const { success, error } = useToast();

const fileInput = ref<HTMLInputElement | null>(null);
const showTrash = ref(false);
const trashPhotos = ref<Photo[]>([]);
const albumId = ref<number | null>(null);
const albumMenu = ref(false);

const selected = ref<Set<number>>(new Set());
let anchor = -1;

const current = computed<Row[]>(() => {
  if (showTrash.value) return trashPhotos.value as Row[];
  let last = '';
  return g.photos.map((p): Row => {
    const day = dayLabel(p.taken_at ?? p.created_at);
    const header = day !== last ? day : undefined;
    last = day;
    return { ...p, _dayHeader: header };
  });
});
const viewer = ref(-1);
const viewerPhoto = computed(() => (viewer.value >= 0 ? g.photos[viewer.value] ?? null : null));
const viewerDate = computed(() => {
  const p = viewerPhoto.value;
  return p ? fullDate(p.taken_at ?? p.created_at) : '';
});

const up = reactive({ active: false, done: 0, total: 0, name: '', frac: 0 });
const upPct = computed(() => (up.total ? Math.min(100, Math.round(((up.done + up.frac) / up.total) * 100)) : 0));
const dragDepth = ref(0);

onMounted(() => { void g.load(); void g.loadAlbums(); window.addEventListener('keydown', onKey); window.addEventListener('focus', onFocus); });
onUnmounted(() => { window.removeEventListener('keydown', onKey); window.removeEventListener('focus', onFocus); });
function onFocus() { if (!document.hidden && !up.active && !showTrash.value) void g.load(albumId.value ?? undefined); }

function dayLabel(iso: string | null): string {
  if (!iso) return '';
  try { return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }); } catch { return ''; }
}
function fullDate(iso: string | null): string {
  if (!iso) return '';
  try { return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }); } catch { return ''; }
}

function pick() { fileInput.value?.click(); }
function onPick(e: Event) { const l = (e.target as HTMLInputElement).files; if (l) void uploadList(l); (e.target as HTMLInputElement).value = ''; }
function onThumbError(e: Event) { (e.target as HTMLImageElement).style.visibility = 'hidden'; }

function hasFiles(e: DragEvent) { return Array.from(e.dataTransfer?.types ?? []).includes('Files'); }
function onDragEnter(e: DragEvent) { if (hasFiles(e)) dragDepth.value++; }
function onDragLeave(e: DragEvent) { if (hasFiles(e)) dragDepth.value = Math.max(0, dragDepth.value - 1); }
function onDrop(e: DragEvent) { dragDepth.value = 0; const l = e.dataTransfer?.files; if (l && l.length) void uploadList(l); }

async function uploadList(list: FileList) {
  const files = Array.from(list).filter((f) => f.type.startsWith('image/'));
  if (!files.length) return;
  Object.assign(up, { active: true, done: 0, total: files.length, name: '', frac: 0 });
  try {
    for (const f of files) {
      up.name = f.name; up.frac = 0;
      await g.upload(f, (fr) => { up.frac = fr; });
      up.frac = 0; up.done++;
    }
    await refresh();
    await g.loadAlbums();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { up.active = false; }
}
function refresh() { return g.load(albumId.value ?? undefined); }

// ---- Multi-select ----
function selectAlbum(id: number | null) { albumId.value = id; clearSelection(); void refresh(); }
function clearSelection() { selected.value = new Set(); anchor = -1; albumMenu.value = false; }
function toggle(id: number) {
  const s = new Set(selected.value);
  if (s.has(id)) s.delete(id); else s.add(id);
  selected.value = s;
}
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
  try {
    const r = await g.createAlbum(name);
    await g.addToAlbum(r.album.id, [...selected.value]);
    await g.loadAlbums(); clearSelection(); success(t('common.saved'));
  } catch { error(t('common.error')); }
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
function openViewer(i: number) { viewer.value = i; }
function step(d: number) {
  if (viewer.value < 0) return;
  const n = g.photos.length;
  if (!n) { viewer.value = -1; return; }
  viewer.value = (viewer.value + d + n) % n;
}
function onKey(e: KeyboardEvent) {
  if (viewer.value < 0) return;
  if (e.key === 'Escape') viewer.value = -1;
  else if (e.key === 'ArrowLeft') step(-1);
  else if (e.key === 'ArrowRight') step(1);
}
async function onFav() {
  const p = viewerPhoto.value;
  if (!p) return;
  const next = !p.favorite;
  try { await g.favorite(p.id, next); p.favorite = next; } catch { error(t('common.error')); }
}
async function onDelete() {
  const p = viewerPhoto.value;
  if (!p) return;
  if (!await confirmAsk(t('gallery.delete_confirm'), { danger: true })) return;
  try { await g.destroy(p.id); viewer.value = -1; await refresh(); await g.loadAlbums(); success(t('common.saved')); } catch { error(t('common.error')); }
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
</script>
