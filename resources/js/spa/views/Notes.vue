<template>
  <div class="flex h-[calc(100vh-8.5rem)] gap-4">
    <!-- Left rail: folders + tags + trash -->
    <Card class="hidden w-60 shrink-0 overflow-y-auto md:block" body-class="p-3">
      <Btn variant="solid" icon="add" class="mb-3 w-full" @click="newNote">{{ t('notes.new_note') }}</Btn>

      <div class="mb-1 flex items-center justify-between px-1">
        <span class="text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('notes.folders') }}</span>
        <button class="text-[var(--ll-muted)] hover:text-[var(--ll-fg)]" :title="t('notes.new_folder')" @click="onNewFolder">
          <Icon name="create_new_folder" :size="18" />
        </button>
      </div>
      <button
        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
        :class="activeFolder === null && !showTrash ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : 'hover:bg-black/[0.04] dark:hover:bg-white/5'"
        @click="selectFolder(null)"
      >
        <Icon name="notes" :size="18" /> {{ t('notes.all_notes') }}
        <span class="ml-auto text-xs text-[var(--ll-muted)]">{{ notesInFolder(null).length }}</span>
      </button>
      <div v-for="f in n.folders" :key="f.id" class="group flex items-center">
        <button
          class="flex flex-1 items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
          :class="activeFolder === f.id && !showTrash ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : 'hover:bg-black/[0.04] dark:hover:bg-white/5'"
          @click="selectFolder(f.id)"
        >
          <Icon name="folder" :size="18" :style="f.color ? { color: f.color } : undefined" />
          <span class="truncate">{{ f.name }}</span>
          <span class="ml-auto text-xs text-[var(--ll-muted)]">{{ notesInFolder(f.id).length }}</span>
        </button>
        <button class="hidden px-1 text-[var(--ll-muted)] hover:text-[var(--ll-fg)] group-hover:block" :title="t('common.rename')" @click="onRenameFolder(f)"><Icon name="edit" :size="15" /></button>
        <button class="hidden px-1 text-red-600 group-hover:block" :title="t('common.delete')" @click="onDeleteFolder(f)"><Icon name="delete" :size="15" /></button>
      </div>

      <div v-if="n.tags.length" class="mb-1 mt-4 px-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('notes.tags') }}</div>
      <button
        v-for="tg in n.tags" :key="tg.name"
        class="mr-1 mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
        :class="activeTag === tg.name ? 'bg-primary-500/20 text-primary-600 dark:text-primary-300' : 'bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10'"
        @click="toggleTag(tg.name)"
      >#{{ tg.name }} <span class="opacity-60">{{ tg.count }}</span></button>

      <button
        class="mt-4 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm"
        :class="showTrash ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : 'hover:bg-black/[0.04] dark:hover:bg-white/5'"
        @click="openTrash"
      ><Icon name="delete" :size="18" /> {{ t('notes.trash') }}</button>
    </Card>

    <!-- Middle: note list -->
    <Card class="w-72 shrink-0 overflow-y-auto" body-class="p-0">
      <div class="border-b border-[var(--ll-border)] p-3">
        <TextField v-model="query" icon="search" :placeholder="t('common.search')" @update:model-value="onSearch" />
      </div>
      <template v-if="!showTrash">
        <button
          v-for="row in visibleNotes" :key="row.id"
          class="block w-full border-b border-[var(--ll-border)] px-4 py-3 text-left last:border-0"
          :class="current?.id === row.id ? 'bg-primary-500/10' : 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]'"
          @click="openNote(row.id)"
        >
          <div class="flex items-center gap-1">
            <Icon v-if="row.pinned" name="push_pin" :size="14" class="text-primary-500" />
            <Icon v-if="row.favorite" name="star" :size="14" class="text-amber-500" />
            <span class="truncate text-sm font-medium">{{ row.title || t('notes.untitled') }}</span>
          </div>
          <div class="mt-0.5 truncate text-xs text-[var(--ll-muted)]">{{ fmtDate(row.updated_at) }}</div>
          <div v-if="row.tags.length" class="mt-1 flex flex-wrap gap-1">
            <span v-for="tg in row.tags" :key="tg" class="rounded bg-black/[0.05] px-1.5 text-[10px] text-[var(--ll-muted)] dark:bg-white/10">#{{ tg }}</span>
          </div>
        </button>
        <div v-if="!visibleNotes.length" class="px-4 py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('notes.empty') }}</div>
      </template>
      <template v-else>
        <div v-for="row in trashNotes" :key="row.id" class="flex items-center gap-2 border-b border-[var(--ll-border)] px-4 py-3 last:border-0">
          <span class="flex-1 truncate text-sm">{{ row.title || t('notes.untitled') }}</span>
          <button class="text-primary-600 hover:underline" :title="t('common.restore')" @click="onRestore(row.id)"><Icon name="restore" :size="18" /></button>
          <button class="text-red-600 hover:underline" :title="t('notes.delete_forever')" @click="onForce(row.id)"><Icon name="delete_forever" :size="18" /></button>
        </div>
        <div v-if="!trashNotes.length" class="px-4 py-8 text-center text-sm text-[var(--ll-muted)]">{{ t('notes.trash_empty') }}</div>
      </template>
    </Card>

    <!-- Right: editor -->
    <Card class="min-w-0 flex-1 overflow-hidden" body-class="flex h-full flex-col p-0">
      <template v-if="current">
        <div class="flex items-center gap-2 border-b border-[var(--ll-border)] p-3">
          <TextField v-model="current.title" :placeholder="t('notes.title_ph')" class="flex-1" />
          <button class="rounded-lg p-2 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :class="current.pinned ? 'text-primary-500' : ''" :title="t('notes.pin')" @click="togglePin"><Icon :name="current.pinned ? 'push_pin' : 'push_pin'" :size="18" /></button>
          <button class="rounded-lg p-2 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :class="current.favorite ? 'text-amber-500' : ''" :title="t('notes.favorite')" @click="toggleFav"><Icon :name="current.favorite ? 'star' : 'star_border'" :size="18" /></button>
          <button class="rounded-lg p-2 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('notes.toggle_preview')" @click="preview = !preview"><Icon :name="preview ? 'edit' : 'visibility'" :size="18" /></button>
          <button v-if="current.id" class="rounded-lg p-2 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('notes.attach')" @click="attachInput?.click()"><Icon name="attach_file" :size="18" /></button>
          <input ref="attachInput" type="file" accept=".pdf,image/*" class="hidden" @change="onAttach">
          <a v-if="current.id" :href="n.exportUrl(current.id)" class="rounded-lg p-2 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('notes.export')"><Icon name="download" :size="18" /></a>
          <Btn variant="solid" size="sm" :loading="saving" @click="save">{{ t('common.save') }}</Btn>
          <button class="rounded-lg p-2 text-red-600 hover:bg-red-500/10" :title="t('common.delete')" @click="onDelete"><Icon name="delete" :size="18" /></button>
        </div>
        <div class="flex min-h-0 flex-1">
          <textarea
            v-if="!preview"
            v-model="current.body"
            class="h-full flex-1 resize-none bg-transparent p-4 font-mono text-sm text-[var(--ll-fg)] focus:outline-none"
            :placeholder="t('notes.body_ph')"
          />
          <div v-else class="ll-prose h-full flex-1 overflow-y-auto p-4" @click="onPreviewClick" v-html="rendered" />
        </div>
        <div v-if="current.id && current.attachments && current.attachments.length" class="border-t border-[var(--ll-border)] p-3">
          <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('notes.attachments') }}</div>
          <div v-for="a in current.attachments" :key="a.id" class="flex items-center gap-2 py-1 text-sm">
            <Icon name="attach_file" :size="16" class="text-[var(--ll-muted)]" />
            <a :href="n.attachmentUrl(current.id, a.id)" target="_blank" rel="noopener" class="flex-1 truncate hover:underline">{{ a.name }}</a>
            <button class="text-red-600 hover:underline" :title="t('common.delete')" @click="onDeleteAttachment(a.id)"><Icon name="delete" :size="16" /></button>
          </div>
        </div>
        <div v-if="current.id && current.backlinks && current.backlinks.length" class="border-t border-[var(--ll-border)] p-3">
          <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[var(--ll-muted)]">{{ t('notes.backlinks') }}</div>
          <button
            v-for="bl in current.backlinks" :key="bl.id"
            class="block w-full rounded-lg px-2 py-1.5 text-left hover:bg-black/[0.04] dark:hover:bg-white/5"
            @click="openNote(bl.id)"
          >
            <div class="truncate text-sm font-medium">{{ bl.title || t('notes.untitled') }}</div>
            <div class="truncate text-xs text-[var(--ll-muted)]">{{ bl.snippet }}</div>
          </button>
        </div>
        <div class="border-t border-[var(--ll-border)] p-3">
          <div class="flex items-center gap-2">
            <span class="text-xs text-[var(--ll-muted)]">{{ t('notes.tags') }}:</span>
            <span v-for="(tg, i) in current.tags" :key="i" class="inline-flex items-center gap-1 rounded-full bg-black/[0.05] px-2 py-0.5 text-xs dark:bg-white/10">
              #{{ tg }} <button class="text-[var(--ll-muted)] hover:text-red-600" @click="current.tags.splice(i, 1)">×</button>
            </span>
            <input v-model="tagInput" class="min-w-24 flex-1 bg-transparent text-sm focus:outline-none" :placeholder="t('notes.add_tag')" @keydown.enter.prevent="addTag" @keydown="onTagKey">
            <Select v-model.number="folderSel" :options="folderOptions" class="w-40" @update:model-value="current.note_folder_id = folderSel || null" />
          </div>
        </div>
      </template>
      <div v-else class="grid h-full place-items-center text-sm text-[var(--ll-muted)]">{{ t('notes.pick_or_create') }}</div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, TextField, Select, Icon } from '@spa/ui';
import { useNotesStore, type NoteDetail, type NoteFolder, type NoteRow } from '@spa/stores/notes';
import { useToast } from '@spa/composables/useToast';
import { confirmAsk, promptAsk } from '@spa/composables/useConfirm';
import { VersionConflict } from '@spa/api/client';
import { renderMarkdown } from '@spa/lib/markdown';

const n = useNotesStore();
const { success, error } = useToast();

const current = ref<NoteDetail | null>(null);
const saving = ref(false);
const preview = ref(false);
const query = ref('');
const searchHits = ref<NoteRow[] | null>(null);
const activeFolder = ref<number | null>(null);
const activeTag = ref<string | null>(null);
const showTrash = ref(false);
const trashNotes = ref<NoteRow[]>([]);
const tagInput = ref('');
const folderSel = ref(0);
const attachInput = ref<HTMLInputElement | null>(null);

// Resolve a wikilink title → note id from the loaded list (case-insensitive,
// latest updated wins) so [[Title]] renders as an internal link.
const notesByTitle = computed(() => {
  const map = new Map<string, number>();
  for (const r of [...n.notes].sort((a, b) => (a.updated_at ?? '').localeCompare(b.updated_at ?? ''))) {
    if (r.title) map.set(r.title.toLowerCase(), r.id);
  }
  return map;
});
const rendered = computed(() => renderMarkdown(current.value?.body ?? '', (title) => notesByTitle.value.get(title.trim().toLowerCase()) ?? null));

function onPreviewClick(e: MouseEvent) {
  const a = (e.target as HTMLElement).closest('a[data-note-id]');
  if (!a) return;
  e.preventDefault();
  const id = Number(a.getAttribute('data-note-id'));
  if (id) openNote(id);
}
const folderOptions = computed(() => [
  { title: t('notes.no_folder'), value: 0 },
  ...n.folders.map((f: NoteFolder) => ({ title: f.name, value: f.id })),
]);

const visibleNotes = computed(() => {
  if (searchHits.value) return searchHits.value;
  return n.notes.filter((r) =>
    (activeFolder.value === null || r.note_folder_id === activeFolder.value)
    && (activeTag.value === null || r.tags.includes(activeTag.value)),
  );
});

function notesInFolder(id: number | null) {
  return n.notes.filter((r) => (id === null ? true : r.note_folder_id === id));
}

function fmtDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleString() : '';
}

onMounted(() => n.load());

function selectFolder(id: number | null) { activeFolder.value = id; showTrash.value = false; searchHits.value = null; query.value = ''; }
function toggleTag(tag: string) { activeTag.value = activeTag.value === tag ? null : tag; }

let searchTimer: ReturnType<typeof setTimeout> | undefined;
function onSearch() {
  clearTimeout(searchTimer);
  const q = query.value.trim();
  if (!q) { searchHits.value = null; return; }
  searchTimer = setTimeout(async () => { try { searchHits.value = await n.search(q); } catch { /* ignore */ } }, 250);
}

async function openNote(id: number) {
  try { current.value = reactive(await n.show(id)); preview.value = false; } catch { error(t('common.error')); }
}
function newNote() {
  current.value = reactive<NoteDetail>({ id: 0, note_folder_id: activeFolder.value, title: '', body: '', tags: [], pinned: false, favorite: false, updated_at: null });
  folderSel.value = activeFolder.value ?? 0;
  preview.value = false;
}

async function save() {
  if (!current.value) return;
  saving.value = true;
  const body = {
    title: current.value.title, body: current.value.body, tags: current.value.tags,
    note_folder_id: current.value.note_folder_id, version: current.value.version,
  };
  try {
    current.value = reactive(current.value.id ? await n.update(current.value.id, body) : await n.create(body));
    await n.load();
    success(t('common.saved'));
  } catch (e) {
    if (e instanceof VersionConflict) { error(t('notes.conflict')); if (current.value?.id) current.value = reactive(await n.show(current.value.id)); }
    else error(t('common.error'));
  } finally { saving.value = false; }
}

async function onDelete() {
  if (!current.value?.id) { current.value = null; return; }
  if (!await confirmAsk(t('notes.delete_confirm'), { danger: true })) return;
  try { await n.destroy(current.value.id); current.value = null; await n.load(); success(t('common.saved')); } catch { error(t('common.error')); }
}

async function togglePin() {
  if (!current.value?.id) { if (current.value) current.value.pinned = !current.value.pinned; return; }
  current.value.pinned = !current.value.pinned;
  try { await n.pin(current.value.id, current.value.pinned); await n.load(); } catch { error(t('common.error')); }
}
async function toggleFav() {
  if (!current.value?.id) { if (current.value) current.value.favorite = !current.value.favorite; return; }
  current.value.favorite = !current.value.favorite;
  try { await n.favorite(current.value.id, current.value.favorite); await n.load(); } catch { error(t('common.error')); }
}

async function onAttach(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file || !current.value?.id) return;
  try {
    const att = await n.attach(current.value.id, file);
    (current.value.attachments ??= []).push(att);
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}
async function onDeleteAttachment(attId: number) {
  if (!current.value?.id) return;
  try {
    await n.deleteAttachment(current.value.id, attId);
    if (current.value.attachments) current.value.attachments = current.value.attachments.filter((a) => a.id !== attId);
  } catch { error(t('common.error')); }
}

function addTag() {
  const v = tagInput.value.trim().replace(/^#/, '');
  if (v && current.value && !current.value.tags.includes(v)) current.value.tags.push(v);
  tagInput.value = '';
}
function onTagKey(e: KeyboardEvent) {
  if (e.key === 'Backspace' && tagInput.value === '' && current.value?.tags.length) current.value.tags.pop();
  else if (e.key === ',') { e.preventDefault(); addTag(); }
}

async function onNewFolder() {
  const name = await promptAsk(t('notes.folder_name'));
  if (!name) return;
  try { await n.createFolder({ name }); await n.load(); } catch { error(t('common.error')); }
}
async function onRenameFolder(f: NoteFolder) {
  const name = await promptAsk(t('common.rename'), { value: f.name });
  if (!name) return;
  try { await n.updateFolder(f.id, { name, version: f.version }); await n.load(); } catch { error(t('common.error')); }
}
async function onDeleteFolder(f: NoteFolder) {
  if (!await confirmAsk(t('notes.folder_delete_confirm'), { danger: true })) return;
  try { await n.deleteFolder(f.id); if (activeFolder.value === f.id) activeFolder.value = null; await n.load(); } catch { error(t('common.error')); }
}

async function openTrash() {
  showTrash.value = true; current.value = null; searchHits.value = null;
  try { const r = await n.trash(); trashNotes.value = r.notes; } catch { error(t('common.error')); }
}
async function onRestore(id: number) { try { await n.restore(id); await openTrash(); await n.load(); } catch { error(t('common.error')); } }
async function onForce(id: number) {
  if (!await confirmAsk(t('notes.delete_forever_confirm'), { danger: true })) return;
  try { await n.forceDelete(id); await openTrash(); } catch { error(t('common.error')); }
}
</script>
