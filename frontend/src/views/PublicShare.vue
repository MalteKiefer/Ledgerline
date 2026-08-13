<template>
  <div class="min-h-screen bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="mx-auto w-full max-w-2xl">
      <div class="mb-6 flex items-center justify-center gap-2.5 pt-6">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</div>

      <!-- Invalid / expired / gone -->
      <Card v-else-if="state === 'invalid' || state === 'expired'" :body-class="'p-8 text-center'">
        <Icon name="link_off" :size="40" class="mx-auto mb-3 text-[var(--ll-muted)]" />
        <p class="text-sm text-[var(--ll-muted)]">{{ state === 'expired' ? t('files.share_expired') : t('files.share_invalid') }}</p>
      </Card>

      <!-- Password gate -->
      <Card v-else-if="state === 'locked'" :body-class="'p-6'">
        <h1 class="mb-1 text-lg font-semibold">{{ meta?.name }}</h1>
        <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('files.share_password_prompt') }}</p>
        <div v-if="unlockError" class="mb-4 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ t('files.share_err_wrong_password') }}</div>
        <form class="flex items-end gap-2" @submit.prevent="unlock">
          <div class="flex-1"><TextField v-model="password" :label="t('auth_ui.password')" type="password" icon="lock" autocomplete="off" @enter="unlock" /></div>
          <Btn type="submit" variant="solid" :loading="unlocking">{{ t('files.share_unlock') }}</Btn>
        </form>
      </Card>

      <!-- Contents -->
      <Card v-else-if="state === 'ready' && manifest" :title="manifest.name">
        <template #actions>
          <Badge tone="gray">{{ rows.length }} {{ t('files.share_items') }}</Badge>
        </template>
        <div v-if="!rows.length" class="py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('files.share_empty') }}</div>
        <ul v-else class="divide-y divide-[var(--ll-border)]">
          <li
            v-for="r in rows" :key="`${r.type}-${r.id}`"
            class="flex items-center gap-3 py-2.5" :style="{ paddingLeft: `${r.depth * 1.25}rem` }"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-lg"
              :style="{ backgroundColor: `${r.tint}1a`, color: r.tint }"
            >
              <Icon :name="r.icon" :size="20" />
            </span>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">{{ r.name }}</div>
              <div v-if="r.type === 'file'" class="text-xs text-[var(--ll-muted)]">{{ formatBytes(r.size ?? 0) }}</div>
            </div>
            <div v-if="r.type === 'file'" class="flex shrink-0 items-center gap-1">
              <a
                :href="rawUrl(r.id, false)" target="_blank" rel="noopener"
                class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('common.open')"
              ><Icon name="open_in_new" :size="18" /></a>
              <a
                v-if="manifest.allowDownload"
                :href="rawUrl(r.id, true)"
                class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('files.share_download')"
              ><Icon name="download" :size="18" /></a>
            </div>
          </li>
        </ul>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn, Badge } from '@spa/ui';
import { api, ApiError } from '@spa/api/client';
import { categoryMsym, categoryTint, formatBytes, FOLDER_TINT } from '@spa/lib/file-categories';

// Same base resolution as the api client — a public link must work whether the
// SPA is same-origin (empty) or points at a split API host (VITE_API_URL).
const BASE = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') ?? '';

interface ShareMeta { found: boolean; kind: 'file' | 'folder'; name: string; needsPassword: boolean; unlocked: boolean; allowDownload: boolean; expiresAt: string | null }
interface ShareFile { id: number; name: string; mime: string | null; size: number; file_folder_id: number | null }
interface ShareFolder { id: number; name: string; parent_id: number | null }
interface ShareManifest { kind: string; name: string; allowDownload: boolean; folders: ShareFolder[]; files: ShareFile[] }
interface Row { type: 'file' | 'folder'; id: number; name: string; depth: number; icon: string; tint: string; size?: number }

const route = useRoute();
const token = typeof route.params.token === 'string' ? route.params.token : String(route.params.token ?? '');

const loading = ref(true);
const state = ref<'invalid' | 'expired' | 'locked' | 'ready'>('invalid');
const meta = ref<ShareMeta | null>(null);
const manifest = ref<ShareManifest | null>(null);
const grant = ref<string | null>(null);
const password = ref('');
const unlocking = ref(false);
const unlockError = ref(false);

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const m = await api.get<ShareMeta>(`/api/v1/file-share/${encodeURIComponent(token)}`);
    if (!m.found) { state.value = 'invalid'; return; }
    meta.value = m;
    if (m.expiresAt && new Date(m.expiresAt).getTime() < Date.now()) { state.value = 'expired'; return; }
    if (m.needsPassword && !m.unlocked) { state.value = 'locked'; return; }
    await loadManifest();
  } catch {
    // Expired shares 404 server-side, so a missing/expired/invalid link all land here.
    state.value = 'invalid';
  } finally {
    loading.value = false;
  }
}

async function loadManifest() {
  const headers = grant.value ? { 'X-Share-Grant': grant.value } : undefined;
  manifest.value = await api.get<ShareManifest>(`/api/v1/file-share/${encodeURIComponent(token)}/manifest`, headers);
  state.value = 'ready';
}

async function unlock() {
  if (!password.value) return;
  unlocking.value = true;
  unlockError.value = false;
  try {
    const r = await api.post<{ ok: boolean; grant: string }>(`/api/v1/file-share/${encodeURIComponent(token)}/unlock`, { password: password.value });
    grant.value = r.grant;
    await loadManifest();
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) unlockError.value = true;
    else state.value = 'invalid';
  } finally {
    unlocking.value = false;
  }
}

/** Public file-bytes URL: no bearer token, carries the unlock grant + optional download flag. */
function rawUrl(fileId: number, download: boolean): string {
  const params = new URLSearchParams();
  if (grant.value) params.set('grant', grant.value);
  if (download) params.set('download', '1');
  const q = params.toString();
  return `${BASE}/api/v1/file-share/${encodeURIComponent(token)}/file/${fileId}/raw${q ? `?${q}` : ''}`;
}

// Flatten the folder subtree into an indented list (folders then their files).
const ROOT = -1;
function group<T>(items: T[], keyOf: (item: T) => number): Map<number, T[]> {
  const map = new Map<number, T[]>();
  for (const item of items) {
    const key = keyOf(item);
    const bucket = map.get(key);
    if (bucket) bucket.push(item);
    else map.set(key, [item]);
  }
  return map;
}

const rows = computed<Row[]>(() => {
  const m = manifest.value;
  if (!m) return [];
  const folderIds = new Set(m.folders.map((f) => f.id));
  const childFolders = group(m.folders, (f) => (f.parent_id !== null && folderIds.has(f.parent_id) ? f.parent_id : ROOT));
  const filesByFolder = group(m.files, (f) => (f.file_folder_id !== null && folderIds.has(f.file_folder_id) ? f.file_folder_id : ROOT));

  const out: Row[] = [];
  const fileRow = (file: ShareFile, depth: number): Row => ({
    type: 'file', id: file.id, name: file.name, depth,
    icon: categoryMsym(file.name, file.mime ?? ''), tint: categoryTint(file.name, file.mime ?? ''), size: file.size,
  });
  const walk = (folder: ShareFolder, depth: number): void => {
    out.push({ type: 'folder', id: folder.id, name: folder.name, depth, icon: 'folder', tint: FOLDER_TINT });
    for (const child of childFolders.get(folder.id) ?? []) walk(child, depth + 1);
    for (const file of filesByFolder.get(folder.id) ?? []) out.push(fileRow(file, depth + 1));
  };
  // Root-level files first (covers a shared single FILE), then the folder tree.
  for (const file of filesByFolder.get(ROOT) ?? []) out.push(fileRow(file, 0));
  for (const folder of childFolders.get(ROOT) ?? []) walk(folder, 0);
  return out;
});
</script>
