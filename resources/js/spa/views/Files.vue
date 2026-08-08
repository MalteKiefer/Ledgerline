<template>
  <div class="d-flex flex-column flex-md-row ga-4" style="min-height:calc(100vh - 120px)">
    <!-- Sidebar -->
    <v-card rounded="xl" border flat width="240" class="flex-shrink-0 d-flex flex-column" style="align-self:flex-start">
      <div class="pa-3">
        <v-btn color="primary" block :prepend-icon="mdiUpload" @click="pickUpload">{{ t('files.upload') }}</v-btn>
        <input ref="uploadInput" type="file" multiple class="d-none" @change="onUpload" >
      </div>
      <v-list density="compact" nav>
        <v-list-item :active="view==='files'" :prepend-icon="mdiFolder" :title="t('files.all_files')" @click="setView('files')" />
        <v-list-item :active="view==='favorites'" :prepend-icon="mdiStar" :title="t('files.favorites')" @click="setView('favorites')" />
        <v-list-item :active="view==='trash'" :prepend-icon="mdiDelete" :title="t('files.trash')" @click="setView('trash')" />
      </v-list>
      <v-divider />
      <div class="pa-3 mt-auto" v-if="s.usage">
        <v-progress-linear v-if="s.usage.quota" :model-value="quotaPct" color="primary" height="6" rounded class="mb-1" />
        <div class="text-caption text-medium-emphasis">{{ fmt(s.usage.used) }}<span v-if="s.usage.quota"> / {{ fmt(s.usage.quota) }}</span></div>
      </div>
    </v-card>

    <!-- Main -->
    <v-card rounded="xl" border flat class="flex-grow-1 d-flex flex-column" style="min-width:0">
      <v-toolbar flat color="surface" density="comfortable">
        <template v-if="view==='files'">
          <v-breadcrumbs :items="crumbs" density="compact" class="pa-0">
            <template #item="{ item }"><a class="cursor-pointer text-primary" @click="cwd = (item as unknown as { value: number | null }).value">{{ item.title }}</a></template>
          </v-breadcrumbs>
        </template>
        <v-toolbar-title v-else>{{ view === 'favorites' ? t('files.favorites') : t('files.trash') }}</v-toolbar-title>
        <v-spacer />
        <v-btn v-if="view==='files'" variant="text" size="small" :prepend-icon="mdiFolderPlus" @click="newFolder">{{ t('files.new_folder') }}</v-btn>
        <v-btn v-if="view==='trash' && trashFiles.length" variant="text" size="small" color="error" @click="emptyTrash">{{ t('files.empty_trash') }}</v-btn>
        <v-btn variant="text" size="small" :icon="layout==='grid' ? mdiViewList : mdiViewGrid" @click="layout = layout==='grid' ? 'list' : 'grid'" />
      </v-toolbar>
      <v-divider />
      <div class="px-4 py-2 border-b">
        <v-text-field v-model="query" :placeholder="t('files.search')" :prepend-inner-icon="mdiMagnify" variant="solo-filled" flat density="compact" hide-details single-line style="max-width:320px" />
      </div>

      <div class="flex-grow-1 overflow-y-auto pa-2">
        <div v-if="!rows.length" class="text-center text-medium-emphasis py-10">{{ view==='trash' ? t('files.trash_empty') : t('files.empty_explorer') }}</div>

        <!-- Grid -->
        <div v-else-if="layout==='grid'" class="d-flex flex-wrap ga-3 pa-2">
          <v-card v-for="row in rows" :key="row._k" width="150" rounded="lg" border flat class="pa-0 overflow-hidden" @dblclick="open(row)">
            <div class="d-flex align-center justify-center" style="height:110px;background:rgb(var(--v-theme-surface-variant))">
              <v-img v-if="row._img" :src="s.thumbUrl(row as never)" cover height="110" width="150" />
              <span v-else class="msym" style="font-size:40px" :style="{ color: row._tint }">{{ row._icon }}</span>
            </div>
            <div class="pa-2 d-flex align-center ga-1">
              <span class="text-caption text-truncate flex-grow-1" :title="row.name">{{ row.name }}</span>
              <v-btn size="x-small" variant="text" :icon="mdiDotsVertical" @click.stop="menuFor(row, $event)" />
            </div>
          </v-card>
        </div>

        <!-- List -->
        <v-list v-else density="comfortable">
          <v-list-item v-for="row in rows" :key="row._k" @click="open(row)">
            <template #prepend>
              <v-avatar :color="row._tint" size="36" rounded="lg">
                <span class="msym" style="font-size:20px;color:#fff">{{ row._icon }}</span>
              </v-avatar>
            </template>
            <v-list-item-title>{{ row.name }}</v-list-item-title>
            <v-list-item-subtitle v-if="!row._folder">{{ fmt((row as never as { size:number }).size) }}</v-list-item-subtitle>
            <template #append>
              <v-btn variant="text" size="small" :icon="mdiDotsVertical" @click.stop="menuFor(row, $event)" />
            </template>
          </v-list-item>
        </v-list>
      </div>
    </v-card>
  </div>

  <!-- Row action menu -->
  <v-menu v-model="menu.show" :target="menu.target" location="bottom end">
    <v-list density="compact">
      <template v-if="menu.row && !menu.row._folder && view!=='trash'">
        <v-list-item :prepend-icon="mdiDownload" :title="t('files.download')" :href="s.rawUrl(menu.row as never)" />
        <v-list-item :prepend-icon="mdiStar" :title="t('files.favorite')" @click="fav(menu.row as never)" />
        <v-list-item :prepend-icon="mdiPencil" :title="t('files.rename')" @click="doRename(menu.row)" />
        <v-list-item :prepend-icon="mdiDelete" base-color="error" :title="t('files.trash')" @click="doTrash(menu.row)" />
      </template>
      <template v-else-if="menu.row && menu.row._folder && view!=='trash'">
        <v-list-item :prepend-icon="mdiPencil" :title="t('files.rename')" @click="doRename(menu.row)" />
        <v-list-item :prepend-icon="mdiDelete" base-color="error" :title="t('files.trash')" @click="doTrash(menu.row)" />
      </template>
      <template v-else-if="menu.row">
        <v-list-item :prepend-icon="mdiRestore" :title="t('files.restore')" @click="doRestore(menu.row)" />
        <v-list-item :prepend-icon="mdiDeleteForever" base-color="error" :title="t('common.delete')" @click="doForce(menu.row)" />
      </template>
    </v-list>
  </v-menu>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiUpload, mdiFolder, mdiStar, mdiDelete, mdiFolderPlus, mdiMagnify, mdiViewGrid, mdiViewList, mdiDotsVertical, mdiDownload, mdiPencil, mdiRestore, mdiDeleteForever } from '@mdi/js';
import { useFilesStore, type FileEntry, type FileFolder } from '@spa/stores/files';
import { categoryMsym, categoryTint, formatBytes, isImage, FOLDER_TINT } from '@spa/lib/file-categories';
import { useToast } from '@spa/composables/useToast';

interface Row { _k: string; _folder: boolean; _icon: string; _tint: string; _img: boolean; id: number; name: string; raw: FileEntry | FileFolder }

const s = useFilesStore();
const { success, error } = useToast();
const view = ref<'files' | 'favorites' | 'trash'>('files');
const layout = ref<'grid' | 'list'>('list');
const cwd = ref<number | null>(null);
const query = ref('');
const uploadInput = ref<HTMLInputElement | null>(null);
const trashFiles = ref<FileEntry[]>([]);
const trashFolders = ref<FileFolder[]>([]);
const menu = ref<{ show: boolean; target: [number, number]; row: Row | null }>({ show: false, target: [0, 0], row: null });

onMounted(() => s.load());

const quotaPct = computed(() => (s.usage?.quota ? Math.min(100, (s.usage.used / s.usage.quota) * 100) : 0));
function fmt(n: number) { return formatBytes(n); }

function mapFile(f: FileEntry): Row { return { _k: `f${f.id}`, _folder: false, _icon: categoryMsym(f.name, f.mime), _tint: categoryTint(f.name, f.mime), _img: isImage(f.name, f.mime), id: f.id, name: f.name, raw: f }; }
function mapFolder(fo: FileFolder): Row { return { _k: `d${fo.id}`, _folder: true, _icon: 'folder', _tint: FOLDER_TINT, _img: false, id: fo.id, name: fo.name, raw: fo }; }

const rows = computed<Row[]>(() => {
  const q = query.value.trim().toLowerCase();
  if (view.value === 'trash') {
    return [...trashFolders.value.map(mapFolder), ...trashFiles.value.map(mapFile)].filter((r) => !q || r.name.toLowerCase().includes(q));
  }
  let files = s.files as FileEntry[];
  let folders: FileFolder[] = [];
  if (view.value === 'favorites') {
    files = files.filter((f) => f.favorite);
  } else {
    folders = (s.folders as FileFolder[]).filter((fo) => fo.file_folder_id === cwd.value);
    files = files.filter((f) => f.file_folder_id === cwd.value);
  }
  let out = [...folders.map(mapFolder), ...files.map(mapFile)];
  if (q) out = out.filter((r) => r.name.toLowerCase().includes(q));
  return out;
});

const crumbs = computed(() => {
  const chain: { title: string; value: number | null }[] = [{ title: t('files.all_files'), value: null }];
  let id = cwd.value;
  const stack: FileFolder[] = [];
  while (id != null) {
    const fo = (s.folders as FileFolder[]).find((x) => x.id === id);
    if (!fo) break;
    stack.unshift(fo); id = fo.file_folder_id;
  }
  stack.forEach((fo) => chain.push({ title: fo.name, value: fo.id }));
  return chain;
});

async function setView(v: 'files' | 'favorites' | 'trash') {
  view.value = v;
  if (v === 'trash') { const r = await s.loadTrash(); trashFiles.value = r.files; trashFolders.value = r.folders; }
}
function open(row: Row) {
  if (row._folder) { view.value = 'files'; cwd.value = row.id; }
  else window.open(s.rawUrl(row.raw as FileEntry), '_blank');
}
function pickUpload() { uploadInput.value?.click(); }
async function onUpload(e: Event) {
  const list = (e.target as HTMLInputElement).files;
  if (!list) return;
  try { for (const f of Array.from(list)) await s.upload(f, cwd.value); await s.load(); success(t('common.saved')); }
  catch { error(t('common.error')); }
}
async function newFolder() {
  const name = prompt(t('files.new_folder'));
  if (name) { await s.createFolder(name, cwd.value); }
}
function menuFor(row: Row, ev: MouseEvent) { menu.value = { show: true, target: [ev.clientX, ev.clientY], row }; }
async function fav(f: FileEntry) { await s.toggleFav(f); await s.load(); }
async function doRename(row: Row) {
  const name = prompt(t('files.rename'), row.name);
  if (!name) return;
  if (row._folder) await s.renameFolder(row.raw as FileFolder, name); else await s.rename(row.raw as FileEntry, name);
  await s.load();
}
async function doTrash(row: Row) {
  if (row._folder) await s.trashFolder(row.raw as FileFolder); else await s.trashFile(row.raw as FileEntry);
  await s.load();
}
async function doRestore(row: Row) { await s.restoreFile(row.id); await setView('trash'); await s.load(); }
async function doForce(row: Row) { if (!confirm(t('common.confirm_delete'))) return; await s.forceFile(row.id); await setView('trash'); }
async function emptyTrash() { if (!confirm(t('common.confirm_delete'))) return; await s.emptyTrash(); await setView('trash'); await s.load(); }
</script>
