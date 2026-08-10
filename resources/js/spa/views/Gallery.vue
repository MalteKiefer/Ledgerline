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

      <div class="p-3">
        <div v-if="!current.length" class="py-20 text-center text-sm text-[var(--ll-muted)]">{{ showTrash ? t('gallery.trash_empty') : t('gallery.empty') }}</div>
        <div v-else class="grid grid-cols-3 gap-1.5 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
          <div v-for="(p, i) in current" :key="p.id" class="group relative aspect-square overflow-hidden rounded-lg bg-black/[0.04] dark:bg-white/5">
            <img :src="g.thumbUrl(p.id)" loading="lazy" class="h-full w-full cursor-pointer object-cover" @click="showTrash ? undefined : openViewer(i)" @error="onThumbError">
            <Icon v-if="p.favorite && !showTrash" name="star" :size="16" class="absolute left-1 top-1 text-amber-400 drop-shadow" />
            <div v-if="showTrash" class="absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-black/40 p-1">
              <button class="rounded p-1 text-white hover:bg-white/20" :title="t('common.restore')" @click="onRestore(p.id)"><Icon name="restore" :size="16" /></button>
              <button class="rounded p-1 text-white hover:bg-white/20" :title="t('gallery.delete_forever')" @click="onForce(p.id)"><Icon name="delete_forever" :size="16" /></button>
            </div>
          </div>
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
          <span class="min-w-0 flex-1 truncate">{{ viewerPhoto?.name }}</span>
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
import { useGalleryStore, type Photo } from '@spa/stores/gallery';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk } from '@spa/composables/useConfirm';

const g = useGalleryStore();
const { success, error } = useToast();

const fileInput = ref<HTMLInputElement | null>(null);
const showTrash = ref(false);
const trashPhotos = ref<Photo[]>([]);
const current = computed(() => (showTrash.value ? trashPhotos.value : g.photos));
const viewer = ref(-1);
const viewerPhoto = computed(() => (viewer.value >= 0 ? g.photos[viewer.value] ?? null : null));

const up = reactive({ active: false, done: 0, total: 0, name: '', frac: 0 });
const upPct = computed(() => (up.total ? Math.min(100, Math.round(((up.done + up.frac) / up.total) * 100)) : 0));
const dragDepth = ref(0);

onMounted(() => { void g.load(); window.addEventListener('keydown', onKey); window.addEventListener('focus', onFocus); });
onUnmounted(() => { window.removeEventListener('keydown', onKey); window.removeEventListener('focus', onFocus); });
function onFocus() { if (!document.hidden && !up.active && !showTrash.value) void g.load(); }

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
    await g.load();
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { up.active = false; }
}

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
  try { await g.destroy(p.id); viewer.value = -1; await g.load(); success(t('common.saved')); } catch { error(t('common.error')); }
}

async function toggleTrash() {
  showTrash.value = !showTrash.value;
  viewer.value = -1;
  if (showTrash.value) { try { trashPhotos.value = await g.trash(); } catch { error(t('common.error')); } }
}
async function onRestore(id: number) { try { await g.restore(id); trashPhotos.value = await g.trash(); await g.load(); } catch { error(t('common.error')); } }
async function onForce(id: number) {
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  try { await g.forceDelete(id); trashPhotos.value = await g.trash(); } catch { error(t('common.error')); }
}
async function onEmpty() {
  if (!await confirmAsk(t('gallery.delete_forever_confirm'), { danger: true })) return;
  try { await g.emptyTrash(); trashPhotos.value = []; } catch { error(t('common.error')); }
}
</script>
