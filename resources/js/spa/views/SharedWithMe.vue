<template>
  <div class="mx-auto w-full max-w-4xl">
    <!-- ===== Share list ===== -->
    <template v-if="!active">
      <div class="mb-4 flex items-center gap-2">
        <h1 class="text-lg font-semibold">{{ t('files.sf_shared_with_me') }}</h1>
        <Badge v-if="shares.length" tone="gray">{{ shares.length }}</Badge>
      </div>
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('files.sf_intro_with_me') }}</p>

      <div v-if="loading" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
      <Card v-else-if="!shares.length" :body-class="'p-10 text-center'">
        <Icon name="folder_shared" :size="40" class="mx-auto mb-3 text-[var(--ll-muted)]" />
        <p class="text-sm text-[var(--ll-muted)]">{{ t('files.sf_no_shared_with_me') }}</p>
      </Card>
      <div v-else class="grid gap-3 sm:grid-cols-2">
        <button
          v-for="sh in shares" :key="sh.id" type="button"
          class="flex items-center gap-3 rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] p-3 text-left transition-colors hover:border-primary-500/40"
          @click="openShare(sh)"
        >
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg" :style="{ backgroundColor: `${shareTint(sh)}1a`, color: shareTint(sh) }">
            <Icon :name="shareIcon(sh)" :size="24" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium">{{ shareName(sh) }}</div>
            <div class="truncate text-xs text-[var(--ll-muted)]">{{ sh.owner?.name || sh.owner?.email || '—' }}</div>
          </div>
          <Badge :tone="sh.role === 'editor' ? 'primary' : 'gray'">{{ t(sh.role === 'editor' ? 'files.sf_role_editor' : 'files.sf_role_viewer') }}</Badge>
        </button>
      </div>
    </template>

    <!-- ===== Browse a shared folder subtree ===== -->
    <template v-else>
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <Btn variant="ghost" size="sm" icon="arrow_back" @click="closeShare">{{ t('files.sf_back') }}</Btn>
        <Badge :tone="active.role === 'editor' ? 'primary' : 'gray'">{{ t(active.role === 'editor' ? 'files.sf_role_editor' : 'files.sf_role_viewer') }}</Badge>
        <div v-if="active.role === 'editor' && !activeIsFile" class="ml-auto">
          <Btn variant="soft" size="sm" icon="upload" :loading="uploading" @click="pickUpload">{{ t('files.sf_upload_here') }}</Btn>
          <input ref="uploadInput" type="file" multiple class="hidden" @change="onUpload" >
        </div>
      </div>

      <!-- File share: a single downloadable file (no tree) -->
      <Card v-if="activeIsFile" :body-class="'p-0'">
        <div v-if="browsing" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
        <div v-else-if="!sharedFile" class="py-12 text-center text-sm text-[var(--ll-muted)]">{{ t('files.sf_empty_folder') }}</div>
        <div v-else class="flex items-center gap-3 p-4">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg" :style="{ backgroundColor: `${categoryTint(sharedFile.name, sharedFile.mime ?? '')}1a`, color: categoryTint(sharedFile.name, sharedFile.mime ?? '') }">
            <Icon :name="categoryMsym(sharedFile.name, sharedFile.mime ?? '')" :size="24" />
          </span>
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium">{{ sharedFile.name }}</div>
            <div class="text-xs text-[var(--ll-muted)] ll-mono">{{ formatBytes(sharedFile.size) }}</div>
          </div>
          <div class="flex items-center gap-1">
            <a :href="s.sharedRawUrl(active.id, sharedFile.id)" target="_blank" rel="noopener" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.sf_open')"><Icon name="open_in_new" :size="18" /></a>
            <a :href="s.sharedRawUrl(active.id, sharedFile.id, true)" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.sf_download')"><Icon name="download" :size="18" /></a>
            <button v-if="active.role === 'editor'" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.sf_rename')" @click="renameSharedFile"><Icon name="drive_file_rename_outline" :size="18" /></button>
          </div>
        </div>
      </Card>

      <Card v-else :body-class="'p-0'">
        <!-- Breadcrumb -->
        <nav class="flex flex-wrap items-center gap-1 border-b border-[var(--ll-border)] px-4 py-2.5 text-sm">
          <template v-for="(c, i) in crumbs" :key="c.id ?? 'root'">
            <Icon v-if="i>0" name="chevron_right" :size="16" class="text-[var(--ll-muted)]" />
            <button
              class="rounded px-1 py-0.5 hover:bg-black/[0.04] dark:hover:bg-white/5"
              :class="i===crumbs.length-1 ? 'font-medium' : 'text-primary-600 dark:text-primary-300'"
              @click="cwd = c.id"
            >{{ c.name }}</button>
          </template>
        </nav>

        <div v-if="browsing" class="py-16 text-center"><Icon name="progress_activity" :size="28" class="animate-spin text-[var(--ll-muted)]" /></div>
        <div v-else-if="!rows.length" class="py-12 text-center text-sm text-[var(--ll-muted)]">{{ t('files.sf_empty_folder') }}</div>
        <table v-else class="w-full text-sm">
          <tbody>
            <tr
              v-for="row in rows" :key="row._k"
              class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/5"
              :class="row._folder ? 'cursor-pointer' : ''"
              @click="row._folder && (cwd = row.id)"
            >
              <td class="py-2 pl-4 pr-3">
                <div class="flex items-center gap-3">
                  <span
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
                    :style="{ backgroundColor: `${row._tint}1a`, color: row._tint }"
                  >
                    <Icon :name="row._icon" :size="20" />
                  </span>
                  <div class="min-w-0">
                    <div class="truncate">{{ row.name }}</div>
                    <div v-if="!row._folder" class="text-xs text-[var(--ll-muted)] ll-mono">{{ formatBytes(row._size) }}</div>
                  </div>
                </div>
              </td>
              <td class="w-24 pr-3 text-right">
                <div v-if="!row._folder" class="flex items-center justify-end gap-1" @click.stop>
                  <a
                    :href="s.sharedRawUrl(active.id, row.id)" target="_blank" rel="noopener"
                    class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.sf_open')"
                  ><Icon name="open_in_new" :size="18" /></a>
                  <a
                    :href="s.sharedRawUrl(active.id, row.id, true)"
                    class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.sf_download')"
                  ><Icon name="download" :size="18" /></a>
                  <template v-if="active.role === 'editor'">
                    <button class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.sf_rename')" @click="renameFile(row)"><Icon name="drive_file_rename_outline" :size="18" /></button>
                    <button class="grid h-8 w-8 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('files.sf_delete')" @click="deleteFile(row)"><Icon name="delete" :size="18" /></button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </Card>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, Btn, Badge } from '@spa/ui';
import { useFilesStore, type SharedWithMeEntry, type SharedBrowse, type SharedFolderNode, type SharedFileNode } from '@spa/stores/files';
import { categoryMsym, categoryTint, formatBytes, FOLDER_TINT } from '@spa/lib/file-categories';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';

interface BrowseRow { _k: string; _folder: boolean; _icon: string; _tint: string; _size: number; id: number; name: string }

const s = useFilesStore();
const { success, error } = useToast();

const loading = ref(true);
const shares = ref<SharedWithMeEntry[]>([]);
const active = ref<SharedWithMeEntry | null>(null);
const browse = ref<SharedBrowse | null>(null);
const browsing = ref(false);
const cwd = ref<number | null>(null);
const uploading = ref(false);
const uploadInput = ref<HTMLInputElement | null>(null);

onMounted(loadShares);

async function loadShares() {
  loading.value = true;
  try { const r = await s.loadSharedWithMe(); shares.value = r.shares; }
  catch { error(t('files.sf_load_failed')); }
  finally { loading.value = false; }
}

// A shared entry is a folder subtree or a lone file; display accordingly.
const shareName = (sh: SharedWithMeEntry) => (sh.kind === 'file' ? sh.file_name : sh.folder_name) || '—';
const shareIcon = (sh: SharedWithMeEntry) => (sh.kind === 'file' ? categoryMsym(sh.file_name ?? '', '') : 'folder_shared');
const shareTint = (sh: SharedWithMeEntry) => (sh.kind === 'file' ? categoryTint(sh.file_name ?? '', '') : FOLDER_TINT);

// True while browsing a file share (single downloadable file, no tree).
const activeIsFile = computed(() => browse.value?.kind === 'file');
const sharedFile = computed(() => browse.value?.file ?? null);

async function openShare(sh: SharedWithMeEntry) {
  active.value = sh;
  await reloadBrowse(true);
}
function closeShare() { active.value = null; browse.value = null; }

async function reloadBrowse(resetCwd = false) {
  if (!active.value) return;
  browsing.value = true;
  try {
    const r = await s.browseShared(active.value.id);
    browse.value = r;
    // The browse payload's role is authoritative (owner/viewer/editor).
    active.value = { ...active.value, role: r.role };
    if (resetCwd) cwd.value = r.root_id;
  } catch { error(t('files.sf_load_failed')); }
  finally { browsing.value = false; }
}

// Breadcrumb chain from the subtree root down to the current folder.
const crumbs = computed<{ id: number | null; name: string }[]>(() => {
  const b = browse.value;
  const rootName = active.value?.folder_name ?? t('files.folder');
  if (!b) return [{ id: null, name: rootName }];
  const byId = new Map((b.folders ?? []).map((f) => [f.id, f]));
  const chain: { id: number | null; name: string }[] = [];
  let id: number | null = cwd.value;
  const guard = new Set<number>();
  while (id != null && id !== b.root_id && !guard.has(id)) {
    guard.add(id);
    const fo = byId.get(id);
    if (!fo) break;
    chain.unshift({ id: fo.id, name: fo.name });
    id = fo.parent_id;
  }
  chain.unshift({ id: b.root_id, name: rootName });
  return chain;
});

// Folders + files whose parent is the current folder.
const rows = computed<BrowseRow[]>(() => {
  const b = browse.value;
  if (!b) return [];
  const here = cwd.value;
  const folders = (b.folders ?? [])
    .filter((f: SharedFolderNode) => f.parent_id === here)
    .map((f): BrowseRow => ({ _k: `d${f.id}`, _folder: true, _icon: 'folder', _tint: FOLDER_TINT, _size: 0, id: f.id, name: f.name }));
  const files = (b.files ?? [])
    .filter((f: SharedFileNode) => f.file_folder_id === here)
    .map((f): BrowseRow => ({ _k: `f${f.id}`, _folder: false, _icon: categoryMsym(f.name, f.mime ?? ''), _tint: categoryTint(f.name, f.mime ?? ''), _size: f.size, id: f.id, name: f.name }));
  return [...folders, ...files];
});

// ---- Editor mutations ----
function pickUpload() { uploadInput.value?.click(); }
async function onUpload(e: Event) {
  const list = (e.target as HTMLInputElement).files;
  if (!list || !active.value) return;
  uploading.value = true;
  try {
    for (const f of Array.from(list)) await s.uploadToShared(active.value.id, f, cwd.value);
    await reloadBrowse();
    success(t('common.saved'));
  } catch { error(t('files.sf_save_failed')); }
  finally { uploading.value = false; if (uploadInput.value) uploadInput.value.value = ''; }
}
async function renameFile(row: BrowseRow) {
  if (!active.value) return;
  const name = await promptAsk(t('files.sf_rename'), { value: row.name });
  if (!name) return;
  try { await s.renameShared(active.value.id, row.id, name); await reloadBrowse(); }
  catch { error(t('files.sf_save_failed')); }
}
async function deleteFile(row: BrowseRow) {
  if (!active.value) return;
  if (!await confirmAsk(t('common.confirm_delete'), { danger: true })) return;
  try { await s.deleteShared(active.value.id, row.id); await reloadBrowse(); }
  catch { error(t('files.sf_save_failed')); }
}

// Rename the lone file of a file-share (editor only; deletion is intentionally
// not offered — a member may never delete the owner's single shared file).
async function renameSharedFile() {
  if (!active.value || !sharedFile.value) return;
  const name = await promptAsk(t('files.sf_rename'), { value: sharedFile.value.name });
  if (!name) return;
  try { await s.renameShared(active.value.id, sharedFile.value.id, name); await reloadBrowse(); }
  catch { error(t('files.sf_save_failed')); }
}
</script>
