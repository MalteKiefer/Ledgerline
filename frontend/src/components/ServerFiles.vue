<template>
  <div>
    <!-- Locked. The password buys a fifteen-minute grant rather than being
         asked for on every click: asking each time trains people to type it
         reflexively, which is worse than asking once and expiring quickly. -->
    <form v-if="!grant" class="max-w-md space-y-3" @submit.prevent="unlock">
      <p class="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">
        {{ t('servers.files_warning') }}
      </p>
      <label class="block">
        <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.terminal_password') }}</span>
        <input
          v-model="password"
          type="password"
          autocomplete="current-password"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
        >
      </label>
      <p v-if="unlockError" class="text-sm text-red-600 dark:text-red-400">{{ unlockError }}</p>
      <Btn type="submit" variant="solid" icon="lock_open" :disabled="unlocking || !password">
        {{ unlocking ? t('common.loading') : t('servers.files_unlock') }}
      </Btn>
    </form>

    <template v-else>
      <!-- Toolbar -->
      <div class="mb-3 flex flex-wrap items-center gap-2">
        <Btn variant="ghost" size="sm" icon="arrow_upward" :disabled="cwd === '/'" :title="t('servers.files_up')" @click="up" />
        <Btn variant="ghost" size="sm" icon="refresh" :disabled="busy" @click="load(cwd)">{{ t('servers.refresh') }}</Btn>
        <Btn variant="ghost" size="sm" icon="create_new_folder" @click="mkdir">{{ t('servers.files_mkdir') }}</Btn>
        <label class="inline-flex">
          <input ref="uploadInput" type="file" class="hidden" @change="onUpload">
          <Btn variant="ghost" size="sm" icon="upload" :disabled="uploading" @click="uploadInput?.click()">
            {{ uploading ? t('servers.files_uploading') : t('servers.files_upload') }}
          </Btn>
        </label>
        <input
          v-model="query"
          :placeholder="t('servers.filter')"
          class="ml-auto w-56 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
        >
        <Btn variant="ghost" size="sm" icon="lock" :title="t('servers.files_lock')" @click="lock" />
      </div>

      <!-- Breadcrumb -->
      <div class="mb-2 flex flex-wrap items-center gap-1 font-mono text-xs">
        <button class="hover:underline" @click="load('/')">/</button>
        <template v-for="(crumb, i) in crumbs" :key="crumb.path">
          <span class="text-[var(--ll-muted)]">/</span>
          <button class="hover:underline" :class="i === crumbs.length - 1 ? 'font-semibold' : ''" @click="load(crumb.path)">{{ crumb.name }}</button>
        </template>
      </div>

      <p v-if="error" class="mb-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <p v-else-if="busy && !entries.length" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>

      <!-- Listing -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-[var(--ll-border)] text-left text-[0.7rem] uppercase tracking-wide text-[var(--ll-muted)]">
              <th class="cursor-pointer py-1.5 pr-3 font-medium select-none" @click="sortBy('name')">{{ t('servers.files_name') }}{{ arrow('name') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 text-right font-medium select-none" @click="sortBy('size')">{{ t('servers.files_size') }}{{ arrow('size') }}</th>
              <th class="py-1.5 pr-3 font-medium">{{ t('servers.files_modified') }}</th>
              <th class="py-1.5 pr-3 font-medium">{{ t('servers.files_perms') }}</th>
              <th class="py-1.5 pr-3 font-medium">{{ t('servers.files_owner') }}</th>
              <th class="py-1.5 text-right font-medium" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in sorted" :key="e.path" class="border-b border-[var(--ll-border)] last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
              <td class="py-2 pr-3">
                <button class="flex items-center gap-2 text-left" @click="open(e)">
                  <Icon :name="iconFor(e)" :size="16" :class="e.type === 'dir' ? 'text-[var(--ll-accent)]' : 'text-[var(--ll-muted)]'" />
                  <span class="font-mono text-xs" :class="e.type === 'dir' ? 'font-semibold' : ''">{{ e.name }}</span>
                </button>
              </td>
              <td class="py-2 pr-3 text-right font-mono text-xs tabular-nums text-[var(--ll-muted)]">{{ e.type === 'dir' ? '—' : humanSize(e.size) }}</td>
              <td class="py-2 pr-3 font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ e.modified }}</td>
              <td class="py-2 pr-3 font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ e.perms }}</td>
              <td class="py-2 pr-3 text-[0.7rem] text-[var(--ll-muted)]">{{ e.owner }}:{{ e.group }}</td>
              <td class="w-40 py-2 text-right">
                <div class="flex justify-end gap-0.5">
                  <Btn v-if="e.type !== 'dir'" variant="ghost" size="sm" icon="download" :title="t('servers.files_download')" @click="download(e)" />
                  <Btn variant="ghost" size="sm" icon="drive_file_rename_outline" :title="t('servers.files_rename')" @click="rename(e)" />
                  <Btn variant="ghost" size="sm" icon="lock_person" :title="t('servers.files_chmod')" @click="chmod(e)" />
                  <Btn variant="ghost" size="sm" icon="delete" :title="t('common.delete')" @click="remove(e)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!busy && !sorted.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </div>
    </template>

    <!-- Viewer / editor -->
    <Modal v-model="viewerOpen" :title="viewerPath" width="900px">
      <div v-if="viewerBinary" class="py-6 text-center text-sm text-[var(--ll-muted)]">
        <!-- Refused rather than shown mangled: a binary opened in an editor and
             saved back is a destroyed file. -->
        {{ t('servers.files_binary') }}
      </div>
      <template v-else>
        <div class="mb-2 flex items-center gap-2">
          <Btn variant="ghost" size="sm" :icon="editing ? 'visibility' : 'edit'" @click="editing = !editing">
            {{ editing ? t('servers.files_view') : t('common.edit') }}
          </Btn>
          <span class="text-xs text-[var(--ll-muted)]">{{ humanSize(viewerSize) }}</span>
          <Btn v-if="editing" variant="solid" size="sm" icon="save" class="ml-auto" :disabled="saving" @click="save">
            {{ saving ? t('common.loading') : t('common.save') }}
          </Btn>
        </div>
        <textarea
          v-if="editing"
          v-model="viewerContent"
          spellcheck="false"
          class="h-[60vh] w-full rounded-lg border border-[var(--ll-border)] bg-transparent p-3 font-mono text-xs"
        />
        <pre
          v-else
          class="h-[60vh] overflow-auto rounded-lg bg-black/[0.05] p-3 font-mono text-xs dark:bg-white/5"
        ><code v-html="highlighted" /></pre>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Icon, Modal } from '@spa/ui';
import { ApiError } from '@spa/api/client';
import { useServersStore, type FileEntry } from '@spa/stores/servers';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';
import { highlightCode } from '@spa/lib/highlight';

const props = defineProps<{ serverId: number }>();

const s = useServersStore();
const { success, error: fail } = useToast();

const grant = ref('');
const password = ref('');
const unlocking = ref(false);
const unlockError = ref('');

const cwd = ref('/');
const entries = ref<FileEntry[]>([]);
const busy = ref(false);
const error = ref('');
const query = ref('');
const uploading = ref(false);
const uploadInput = ref<HTMLInputElement | null>(null);

const sortKey = ref<'name' | 'size'>('name');
const sortDesc = ref(false);

const viewerOpen = ref(false);
const viewerPath = ref('');
const viewerContent = ref('');
const viewerBinary = ref(false);
const viewerSize = ref(0);
const editing = ref(false);
const saving = ref(false);

const crumbs = computed(() => {
  const parts = cwd.value.split('/').filter(Boolean);

  return parts.map((name, i) => ({ name, path: '/' + parts.slice(0, i + 1).join('/') }));
});

const sorted = computed(() => {
  const q = query.value.trim().toLowerCase();
  const rows = q ? entries.value.filter((e) => e.name.toLowerCase().includes(q)) : entries.value.slice();
  const dir = sortDesc.value ? -1 : 1;

  // Directories first regardless of the sort: that is how every file manager
  // behaves, and mixing them makes a deep tree unusable.
  return rows.sort((a, b) => {
    if ((a.type === 'dir') !== (b.type === 'dir')) return a.type === 'dir' ? -1 : 1;
    if (sortKey.value === 'size') return (a.size - b.size) * dir;

    return a.name.localeCompare(b.name) * dir;
  });
});

const highlighted = computed(() => highlightCode(viewerContent.value, viewerPath.value));

function sortBy(key: 'name' | 'size') {
  if (sortKey.value === key) sortDesc.value = !sortDesc.value;
  else {
    sortKey.value = key;
    sortDesc.value = key === 'size';
  }
}

function arrow(key: 'name' | 'size'): string {
  return sortKey.value === key ? (sortDesc.value ? ' ↓' : ' ↑') : '';
}

function iconFor(e: FileEntry): string {
  if (e.type === 'dir') return 'folder';
  if (e.type === 'link') return 'link';

  return 'description';
}

function humanSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB', 'TB'];
  let v = bytes / 1024;
  let i = 0;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i += 1; }

  return `${v.toFixed(v < 10 ? 1 : 0)} ${units[i]}`;
}

async function unlock() {
  unlocking.value = true;
  unlockError.value = '';
  try {
    const r = await s.filesUnlock(props.serverId, password.value);
    grant.value = r.token;
    password.value = '';
    await load('/');
  } catch (e) {
    unlockError.value = e instanceof ApiError && e.status === 422
      ? t('servers.terminal_bad_password')
      : t('servers.status_fail');
  } finally {
    unlocking.value = false;
  }
}

async function lock() {
  if (grant.value) await s.filesLock(props.serverId, grant.value).catch(() => {});
  grant.value = '';
  entries.value = [];
  cwd.value = '/';
}

async function load(path: string) {
  busy.value = true;
  error.value = '';
  try {
    const r = await s.filesList(props.serverId, grant.value, path);
    if (r.ok) {
      cwd.value = r.path;
      entries.value = r.entries;
    } else {
      error.value = errorText(r.error);
    }
  } catch (e) {
    // An expired grant is not a failure to explain away — ask again.
    if (e instanceof ApiError && e.status === 403) grant.value = '';
    else error.value = t('servers.status_fail');
  } finally {
    busy.value = false;
  }
}

function up() {
  const parts = cwd.value.split('/').filter(Boolean);
  parts.pop();
  void load('/' + parts.join('/'));
}

async function open(e: FileEntry) {
  if (e.type === 'dir') { await load(e.path); return; }

  viewerPath.value = e.path;
  viewerContent.value = '';
  viewerBinary.value = false;
  viewerSize.value = e.size;
  editing.value = false;
  viewerOpen.value = true;

  try {
    const r = await s.filesRead(props.serverId, grant.value, e.path);
    viewerBinary.value = r.binary || (!r.ok && r.error === 'too_large');
    viewerContent.value = r.content;
    viewerSize.value = r.size;
    if (!r.ok && !r.binary && r.error !== 'too_large') fail(errorText(r.error));
  } catch {
    fail(t('servers.status_fail'));
  }
}

async function save() {
  saving.value = true;
  try {
    const r = await s.filesWrite(props.serverId, grant.value, viewerPath.value, viewerContent.value);
    if (r.ok) { success(t('servers.files_saved')); editing.value = false; }
    else fail(errorText(r.error));
  } catch {
    fail(t('servers.status_fail'));
  } finally {
    saving.value = false;
  }
}

async function download(e: FileEntry) {
  try {
    const blob = await s.filesDownload(props.serverId, grant.value, e.path);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = e.name;
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    fail(t('servers.status_fail'));
  }
}

async function onUpload(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file) return;

  uploading.value = true;
  try {
    const r = await s.filesUpload(props.serverId, grant.value, cwd.value, file);
    if (r.ok) { success(t('servers.files_uploaded')); await load(cwd.value); }
    else fail(errorText(r.error));
  } catch {
    fail(t('servers.status_fail'));
  } finally {
    uploading.value = false;
  }
}

async function mkdir() {
  const name = await promptAsk(t('servers.files_mkdir'));
  if (!name) return;
  await mutate({ action: 'mkdir', path: `${cwd.value === '/' ? '' : cwd.value}/${name}` });
}

async function rename(e: FileEntry) {
  const name = await promptAsk(t('servers.files_rename'), { value: e.name });
  if (!name || name === e.name) return;
  const dir = cwd.value === '/' ? '' : cwd.value;
  await mutate({ action: 'rename', path: e.path, target: `${dir}/${name}` });
}

async function chmod(e: FileEntry) {
  const mode = await promptAsk(t('servers.files_chmod'), { value: e.perms.slice(1) === '' ? '' : '644', placeholder: '644' });
  if (!mode) return;
  await mutate({ action: 'chmod', path: e.path, mode });
}

async function remove(e: FileEntry) {
  if (!(await confirmAsk(t('servers.files_delete_confirm', { name: e.name })))) return;
  // rmdir only removes an empty directory, which is the honest behaviour here:
  // a recursive delete behind one click is how a machine loses a filesystem.
  await mutate({ action: e.type === 'dir' ? 'rmdir' : 'rm', path: e.path });
}

async function mutate(body: { action: string; path: string; target?: string; mode?: string }) {
  try {
    const r = await s.filesMutate(props.serverId, grant.value, body);
    if (r.ok) { success(t('servers.files_done')); await load(cwd.value); }
    else fail(errorText(r.error));
  } catch {
    fail(t('servers.status_fail'));
  }
}

function errorText(code: string | null): string {
  if (!code) return '';
  const key = `servers.files_err_${code}`;
  const text = t(key);

  return text === key ? code : text;
}

onBeforeUnmount(() => {
  // Leaving should not leave the filesystem unlocked.
  if (grant.value) void s.filesLock(props.serverId, grant.value).catch(() => {});
});

defineExpose({ close: lock });
</script>
