<template>
  <div>
    <!-- Locked. The password buys a fifteen-minute grant rather than being
         asked for on every click: asking each time trains people to type it
         reflexively, which is worse than asking once and expiring quickly. -->
<!-- Same shape as the terminal's lock screen: both ask the same question
         for the same reason, so they should not look like two different
         features. -->
    <div v-if="!grant" class="mx-auto max-w-md py-8">
      <div class="mb-3 flex items-center gap-2">
        <Icon name="folder_open" :size="20" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('servers.files_title') }}</h2>
      </div>
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('servers.files_unlock_intro') }}</p>
      <form @submit.prevent="unlock">
        <TextField v-model="password" type="password" :label="t('account.password_current')" autocomplete="current-password" autofocus />
        <p v-if="unlockError" class="mt-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ unlockError }}</p>
        <Btn class="mt-3" type="submit" variant="solid" icon="lock_open" :disabled="unlocking || !password">
          {{ unlocking ? t('common.loading') : t('servers.files_unlock') }}
        </Btn>
      </form>
      <p class="mt-4 text-[0.7rem] leading-relaxed text-[var(--ll-muted)]">{{ t('servers.files_warning') }}</p>
    </div>

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
<Btn
          v-if="selected.size"
          variant="ghost"
          size="sm"
          icon="download"
          :disabled="downloading"
          @click="downloadSelected"
        >{{ downloading ? t('servers.files_downloading') : t('servers.files_download_n', { n: String(selected.size) }) }}</Btn>
        <template v-if="selected.size && packFormats.length">
          <Select v-model="packFormat" class="w-32" :options="packOptions" />
          <Btn
            variant="ghost"
            size="sm"
            icon="folder_zip"
            :disabled="downloading"
            @click="downloadArchive"
          >{{ t('servers.files_pack') }}</Btn>
        </template>
        <Btn
          v-if="selected.size"
          variant="ghost"
          size="sm"
          icon="delete"
          @click="removeSelected"
        >{{ t('servers.files_delete_n', { n: String(selected.size) }) }}</Btn>
        <input
          v-model="query"
          :placeholder="t('servers.filter')"
          class="ml-auto w-56 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm"
        >
        <Btn variant="ghost" size="sm" icon="lock" :title="t('servers.files_lock')" @click="lock" />
      </div>

      <!-- Breadcrumb -->
<!-- The root is the first slash, not a slash plus a separator: printing
           both gave "/ / srv". -->
      <div class="mb-2 flex flex-wrap items-center gap-1 font-mono text-xs">
        <button class="rounded px-1 hover:bg-black/5 hover:underline dark:hover:bg-white/10" @click="load('/')">/</button>
        <template v-for="(crumb, i) in crumbs" :key="crumb.path">
          <span v-if="i > 0" class="text-[var(--ll-muted)]">/</span>
          <button
            class="rounded px-1 hover:bg-black/5 hover:underline dark:hover:bg-white/10"
            :class="i === crumbs.length - 1 ? 'font-semibold' : ''"
            @click="load(crumb.path)"
          >{{ crumb.name }}</button>
        </template>
      </div>

      <p v-if="error" class="mb-3 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      <p v-else-if="busy && !entries.length" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>

      <!-- Listing -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
<tr class="border-b border-[var(--ll-border)] text-left text-[0.7rem] uppercase tracking-wide text-[var(--ll-muted)]">
              <th class="w-8 py-1.5">
                <input type="checkbox" class="accent-primary-500" :checked="allSelected" @change="toggleAll">
              </th>
              <th class="cursor-pointer py-1.5 pr-3 font-medium select-none" @click="sortBy('name')">{{ t('servers.files_name') }}{{ arrow('name') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 text-right font-medium select-none" @click="sortBy('size')">{{ t('servers.files_size') }}{{ arrow('size') }}</th>
              <th class="py-1.5 pr-3 font-medium">{{ t('servers.files_modified') }}</th>
              <th class="py-1.5 pr-3 font-medium">{{ t('servers.files_perms') }}</th>
              <th class="cursor-pointer py-1.5 pr-3 font-medium select-none" @click="sortBy('owner')">{{ t('servers.files_owner') }}{{ arrow('owner') }}</th>
              <th class="py-1.5 text-right font-medium" />
            </tr>
          </thead>
          <tbody>
<!-- The whole row opens the entry, not just the name: a two-word file
                 name is a tiny target, and everything else in the row already
                 describes the same thing. The checkbox and the action buttons
                 stop the click so they keep doing their own job. -->
            <tr
              v-for="e in sorted"
              :key="e.path"
              class="cursor-pointer border-b border-[var(--ll-border)] last:border-0 transition-colors hover:bg-black/[0.02] active:bg-[var(--ll-accent)]/10 dark:hover:bg-white/[0.03]"
              :class="[selected.has(e.path) ? 'bg-[var(--ll-accent)]/5' : '', opening === e.path ? 'bg-[var(--ll-accent)]/10' : '']"
              @click="open(e)"
            >
              <td class="py-2" @click.stop>
                <input type="checkbox" class="accent-primary-500" :checked="selected.has(e.path)" @change="toggle(e)">
              </td>
              <td class="py-2 pr-3">
                <div class="flex items-center gap-2">
                  <Icon
                    :name="opening === e.path ? 'hourglass_empty' : iconFor(e)"
                    :size="16"
                    :class="e.type === 'dir' ? 'text-[var(--ll-accent)]' : 'text-[var(--ll-muted)]'"
                  />
                  <span class="font-mono text-xs" :class="e.type === 'dir' ? 'font-semibold' : ''">{{ e.name }}</span>
                </div>
              </td>
              <td class="py-2 pr-3 text-right font-mono text-xs tabular-nums text-[var(--ll-muted)]">{{ e.type === 'dir' ? '—' : humanSize(e.size) }}</td>
              <td class="py-2 pr-3 font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ e.modified }}</td>
              <td class="py-2 pr-3 font-mono text-[0.7rem] text-[var(--ll-muted)]">{{ e.perms }}</td>
              <td class="py-2 pr-3 text-[0.7rem] text-[var(--ll-muted)]">{{ e.owner }}:{{ e.group }}</td>
              <td class="w-40 py-2 text-right" @click.stop>
                <div class="flex justify-end gap-0.5">
                  <Btn v-if="e.type !== 'dir'" variant="ghost" size="sm" icon="download" :title="t('servers.files_download')" @click="download(e)" />
                  <Btn
                    v-if="canExtract(e)"
                    variant="ghost"
                    size="sm"
                    icon="unarchive"
                    :disabled="extracting"
                    :title="t('servers.files_extract')"
                    @click="extract(e)"
                  />
                  <Btn variant="ghost" size="sm" icon="drive_file_rename_outline" :title="t('servers.files_rename')" @click="rename(e)" />
                  <Btn variant="ghost" size="sm" icon="lock_person" :title="t('servers.files_perm_title')" @click="openPerms(e)" />
                  <Btn variant="ghost" size="sm" icon="delete" :title="t('common.delete')" @click="remove(e)" />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!busy && !sorted.length" class="py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</p>
      </div>
    </template>

<!-- Permissions: mode, ownership and ACLs in one place, because they are
         one question. Boxes rather than only digits - 0755 is unreadable until
         you have done it a hundred times, and the digits stay in sync for when
         you have. -->
    <Modal v-model="permsOpen" :title="t('servers.files_perm_title')" width="720px">
      <p v-if="permsBusy" class="py-4 text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
      <template v-else-if="perms">
        <p class="mb-3 truncate font-mono text-xs text-[var(--ll-muted)]">{{ permsPath }}</p>

        <table class="mb-3 w-full text-sm">
          <thead>
            <tr class="text-left text-[0.7rem] uppercase tracking-wide text-[var(--ll-muted)]">
              <th class="py-1 font-medium" />
              <th class="py-1 text-center font-medium">{{ t('servers.perm_read') }}</th>
              <th class="py-1 text-center font-medium">{{ t('servers.perm_write') }}</th>
              <th class="py-1 text-center font-medium">{{ t('servers.perm_exec') }}</th>
              <th class="py-1 text-center font-medium">{{ t('servers.perm_special') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cls in ['owner', 'group', 'other'] as const" :key="cls" class="border-t border-[var(--ll-border)]">
              <td class="py-1.5 pr-3 text-xs">{{ t(`servers.perm_${cls}`) }}</td>
              <td class="py-1.5 text-center"><input v-model="bits[cls].r" type="checkbox" class="accent-primary-500"></td>
              <td class="py-1.5 text-center"><input v-model="bits[cls].w" type="checkbox" class="accent-primary-500"></td>
              <td class="py-1.5 text-center"><input v-model="bits[cls].x" type="checkbox" class="accent-primary-500"></td>
              <td class="py-1.5 text-center">
                <label class="inline-flex items-center gap-1 text-[0.7rem] text-[var(--ll-muted)]">
                  <input v-model="bits[cls].s" type="checkbox" class="accent-primary-500">
                  {{ cls === 'owner' ? 'setuid' : cls === 'group' ? 'setgid' : 'sticky' }}
                </label>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="mb-4 flex flex-wrap items-end gap-3">
          <label class="w-28">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.perm_octal') }}</span>
            <input
              :value="octal"
              class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 font-mono text-sm"
              @input="setOctal(($event.target as HTMLInputElement).value)"
            >
          </label>
          <label class="w-44">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.perm_owner_label') }}</span>
            <input v-model="ownerName" list="ll-users" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
            <datalist id="ll-users"><option v-for="u in perms.users" :key="u" :value="u" /></datalist>
          </label>
          <label class="w-44">
            <span class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('servers.perm_group_label') }}</span>
            <input v-model="groupName" list="ll-groups" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 text-sm">
            <datalist id="ll-groups"><option v-for="g in perms.groups" :key="g" :value="g" /></datalist>
          </label>
          <label class="flex items-center gap-2 pb-2 text-xs">
            <input v-model="recursive" type="checkbox" class="accent-primary-500">{{ t('servers.perm_recursive') }}
          </label>
        </div>

        <!-- ACLs. Absent tooling is reported as unreadable, not as "no ACLs":
             a host without the acl package is not a host without access
             control, and saying otherwise would be the same lie as calling an
             unreadable firewall an empty one. -->
        <div class="border-t border-[var(--ll-border)] pt-3">
          <h3 class="mb-2 text-xs font-semibold">{{ t('servers.perm_acl') }}</h3>
          <p v-if="!perms.acl_supported" class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.perm_acl_unsupported') }}</p>
          <template v-else>
            <p v-if="!perms.acl.length" class="text-[0.7rem] text-[var(--ll-muted)]">{{ t('servers.perm_acl_none') }}</p>
            <div v-for="entry in perms.acl" :key="entry" class="flex items-center gap-2 border-b border-[var(--ll-border)] py-1 last:border-0">
              <span class="font-mono text-xs">{{ entry }}</span>
              <Btn variant="ghost" size="sm" icon="delete" class="ml-auto" :title="t('common.delete')" @click="dropAcl(entry)" />
            </div>
            <div class="mt-2 flex flex-wrap items-end gap-2">
              <input
                v-model="aclEntry"
                placeholder="u:alice:rwx"
                class="w-56 rounded-lg border border-[var(--ll-border)] bg-transparent px-2.5 py-1.5 font-mono text-sm"
              >
              <Btn variant="ghost" size="sm" icon="add" :disabled="!aclEntry" @click="addAcl">{{ t('servers.perm_acl_add') }}</Btn>
            </div>
          </template>
        </div>

        <p v-if="permsError" class="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-700 dark:text-amber-400">{{ permsError }}</p>
      </template>

      <template #footer>
        <Btn variant="ghost" @click="permsOpen = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :disabled="permsSaving || !perms" @click="savePerms">
          {{ permsSaving ? t('common.loading') : t('common.save') }}
        </Btn>
      </template>
    </Modal>

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
import { Btn, Icon, Modal, Select, TextField } from '@spa/ui';
import { ApiError } from '@spa/api/client';
import { useServersStore, type FileEntry, type FilePermissions } from '@spa/stores/servers';
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

const sortKey = ref<'name' | 'size' | 'owner'>('name');
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
    if (sortKey.value === 'owner') return `${a.owner}:${a.group}`.localeCompare(`${b.owner}:${b.group}`) * dir;

    return a.name.localeCompare(b.name) * dir;
  });
});

const highlighted = computed(() => highlightCode(viewerContent.value, viewerPath.value));

function sortBy(key: 'name' | 'size' | 'owner') {
  if (sortKey.value === key) sortDesc.value = !sortDesc.value;
  else {
    sortKey.value = key;
    // Numbers are interesting from the top, names from the start.
    sortDesc.value = key === 'size';
  }
}

function arrow(key: 'name' | 'size' | 'owner'): string {
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
    await loadArchiveTools();
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
      // A selection that survived a directory change would act on paths the
      // operator can no longer see.
      selected.value = new Set();
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
  opening.value = e.path;
  if (e.type === 'dir') {
    await load(e.path);
    opening.value = '';

    return;
  }

  viewerPath.value = e.path;
  viewerContent.value = '';
  viewerBinary.value = false;
  viewerSize.value = e.size;
  editing.value = false;
  viewerOpen.value = true;

  try {
    const r = await s.filesRead(props.serverId, grant.value, e.path);
    viewerBinary.value = r.binary || (!r.ok && r.error === 'too_large');
    // Base64 on the wire: the framework trims request strings (which eats a
    // trailing newline) and JSON cannot carry bytes that are not valid UTF-8.
    viewerContent.value = r.content ? decodeText(r.content) : '';
    viewerSize.value = r.size;
    if (!r.ok && !r.binary && r.error !== 'too_large') fail(errorText(r.error));
  } catch {
    fail(t('servers.status_fail'));
  } finally {
    opening.value = '';
  }
}

async function save() {
  saving.value = true;
  try {
    const r = await s.filesWrite(props.serverId, grant.value, viewerPath.value, encodeText(viewerContent.value));
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

/** base64 -> text, via UTF-8 so multi-byte characters survive the trip. */
function decodeText(b64: string): string {
  const bin = atob(b64);
  const bytes = Uint8Array.from(bin, (c) => c.charCodeAt(0));

  return new TextDecoder().decode(bytes);
}

/** text -> base64, the same way round. */
function encodeText(text: string): string {
  const bytes = new TextEncoder().encode(text);
  let bin = '';
  for (const b of bytes) bin += String.fromCharCode(b);

  return btoa(bin);
}

// ---- permissions ----

const permsOpen = ref(false);
const permsPath = ref('');
const perms = ref<FilePermissions | null>(null);
const permsBusy = ref(false);
const permsSaving = ref(false);
const permsError = ref('');
const ownerName = ref('');
const groupName = ref('');
const recursive = ref(false);
const aclEntry = ref('');

const bits = ref({
  owner: { r: false, w: false, x: false, s: false },
  group: { r: false, w: false, x: false, s: false },
  other: { r: false, w: false, x: false, s: false },
});

/** The boxes and the digits are one value seen two ways, so they stay in sync. */
const octal = computed(() => {
  const b = bits.value;
  const special = (b.owner.s ? 4 : 0) + (b.group.s ? 2 : 0) + (b.other.s ? 1 : 0);
  const digit = (c: { r: boolean; w: boolean; x: boolean }) => (c.r ? 4 : 0) + (c.w ? 2 : 0) + (c.x ? 1 : 0);

  return `${special}${digit(b.owner)}${digit(b.group)}${digit(b.other)}`;
});

function setOctal(value: string) {
  const clean = value.replace(/[^0-7]/g, '').slice(-4).padStart(4, '0');
  const [sp, o, g, ot] = clean.split('').map(Number) as [number, number, number, number];
  const apply = (n: number) => ({ r: !!(n & 4), w: !!(n & 2), x: !!(n & 1) });
  bits.value = {
    owner: { ...apply(o), s: !!(sp & 4) },
    group: { ...apply(g), s: !!(sp & 2) },
    other: { ...apply(ot), s: !!(sp & 1) },
  };
}

async function openPerms(e: FileEntry) {
  permsPath.value = e.path;
  perms.value = null;
  permsError.value = '';
  recursive.value = false;
  aclEntry.value = '';
  permsOpen.value = true;
  permsBusy.value = true;
  try {
    const r = await s.filesPermissions(props.serverId, grant.value, e.path);
    perms.value = r;
    if (r.ok) {
      setOctal(r.mode);
      ownerName.value = r.owner;
      groupName.value = r.group;
    } else {
      permsError.value = errorText(r.error);
    }
  } catch {
    permsError.value = t('servers.status_fail');
  } finally {
    permsBusy.value = false;
  }
}

async function savePerms() {
  permsSaving.value = true;
  permsError.value = '';
  try {
    const r = await s.filesSetPermissions(props.serverId, grant.value, {
      path: permsPath.value,
      mode: octal.value,
      owner: ownerName.value,
      group: groupName.value,
      recursive: recursive.value,
    });
    if (r.ok) { success(t('servers.files_done')); permsOpen.value = false; await load(cwd.value); }
    else permsError.value = r.output || errorText(r.error);
  } catch {
    permsError.value = t('servers.status_fail');
  } finally {
    permsSaving.value = false;
  }
}

async function addAcl() {
  await aclChange([aclEntry.value], false);
  aclEntry.value = '';
}

async function dropAcl(entry: string) {
  await aclChange([entry], true);
}

async function aclChange(entries: string[], remove: boolean) {
  permsError.value = '';
  try {
    const r = await s.filesSetPermissions(props.serverId, grant.value, {
      path: permsPath.value,
      acl: entries,
      acl_remove: remove,
      recursive: recursive.value,
    });
    if (r.ok) {
      const fresh = await s.filesPermissions(props.serverId, grant.value, permsPath.value);
      perms.value = fresh;
    } else {
      permsError.value = r.output || errorText(r.error);
    }
  } catch {
    permsError.value = t('servers.status_fail');
  }
}

// ---- selection ----

const selected = ref(new Set<string>());
const downloading = ref(false);
const extracting = ref(false);

/**
 * What this host can pack and unpack, asked rather than assumed: a minimal
 * machine often has tar and gzip and nothing else, and a zip button that always
 * fails is worse than no button.
 */
const packFormats = ref<string[]>([]);
const extractFormats = ref<string[]>([]);
const packFormat = ref('tar.gz');

const packOptions = computed(() => packFormats.value.map((f) => ({ value: f, title: f })));

/** The extension decides the format; anything longer wins so .tar.gz beats .gz. */
function formatOf(name: string): string | null {
  const lower = name.toLowerCase();
  const all = ['tar.gz', 'tar.xz', 'tar.bz2', 'tar.zst', 'tgz', 'tar', 'zip', '7z', 'rar', 'gz', 'xz', 'bz2', 'zst'];
  const hit = all.find((f) => lower.endsWith(`.${f}`));

  return hit === 'tgz' ? 'tar.gz' : (hit ?? null);
}

function canExtract(e: FileEntry): boolean {
  if (e.type === 'dir') return false;
  const format = formatOf(e.name);

  return format !== null && extractFormats.value.includes(format);
}

async function loadArchiveTools() {
  try {
    const tools = await s.archiveTools(props.serverId, grant.value);
    packFormats.value = tools.pack ?? [];
    extractFormats.value = tools.extract ?? [];
    if (packFormats.value.length && !packFormats.value.includes(packFormat.value)) {
      packFormat.value = packFormats.value[0];
    }
  } catch {
    // Not being able to ask is not a failure worth a message: the buttons
    // simply do not appear.
    packFormats.value = [];
    extractFormats.value = [];
  }
}

/** Pack the whole selection into one file, built on the host. */
async function downloadArchive() {
  downloading.value = true;
  try {
    const paths = [...selected.value];
    const blob = await s.filesArchive(props.serverId, grant.value, paths, packFormat.value);
    const base = paths.length === 1 ? (paths[0].split('/').pop() || 'archive') : (cwd.value.split('/').pop() || 'archive');
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${base}.${packFormat.value}`;
    a.click();
    URL.revokeObjectURL(url);
    selected.value = new Set();
  } catch {
    fail(t('servers.status_fail'));
  } finally {
    downloading.value = false;
  }
}

/**
 * Unpack beside the archive, into a directory named after it — never straight
 * into the current one, where an archive holding two hundred loose files would
 * scatter them over somebody's working directory.
 */
async function extract(e: FileEntry) {
  if (!(await confirmAsk(t('servers.files_extract_confirm', { name: e.name })))) return;

  extracting.value = true;
  try {
    const res = await s.filesExtract(props.serverId, grant.value, e.path);
    if (res.ok) {
      success(t('servers.files_extracted', { path: res.dest ?? '' }));
      await load(cwd.value);
    } else {
      fail(t(`servers.err_${res.error}`) === `servers.err_${res.error}` ? String(res.error) : t(`servers.err_${res.error}`));
    }
  } catch {
    fail(t('servers.status_fail'));
  } finally {
    extracting.value = false;
  }
}
const opening = ref('');

const allSelected = computed(() => sorted.value.length > 0 && sorted.value.every((e) => selected.value.has(e.path)));

function toggle(e: FileEntry) {
  const next = new Set(selected.value);
  if (next.has(e.path)) next.delete(e.path);
  else next.add(e.path);
  selected.value = next;
}

function toggleAll() {
  selected.value = allSelected.value ? new Set() : new Set(sorted.value.map((e) => e.path));
}

/**
 * Download everything selected.
 *
 * A directory is fetched as a tar built on the host, because there is no
 * sensible way to hand a browser a tree — and building the archive there means
 * one transfer rather than one per file.
 */
async function downloadSelected() {
  downloading.value = true;
  try {
    for (const path of selected.value) {
      const entry = entries.value.find((e) => e.path === path);
      if (!entry) continue;
      if (entry.type === 'dir') await downloadDir(entry);
      else await download(entry);
    }
    selected.value = new Set();
  } finally {
    downloading.value = false;
  }
}

async function downloadDir(e: FileEntry) {
  try {
    const blob = await s.filesDownloadDir(props.serverId, grant.value, e.path);
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${e.name}.tar.gz`;
    a.click();
    URL.revokeObjectURL(url);
  } catch {
    fail(t('servers.status_fail'));
  }
}

async function removeSelected() {
  const names = [...selected.value].map((p) => p.split('/').pop()).join(', ');
  if (!(await confirmAsk(t('servers.files_delete_confirm', { name: names })))) return;

  for (const path of selected.value) {
    const entry = entries.value.find((e) => e.path === path);
    if (!entry) continue;
    await s.filesMutate(props.serverId, grant.value, {
      action: entry.type === 'dir' ? 'rmdir' : 'rm',
      path,
    }).catch(() => {});
  }
  selected.value = new Set();
  await load(cwd.value);
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
