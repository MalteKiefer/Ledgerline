<template>
  <div
    class="relative flex h-[calc(100vh-8.5rem)] gap-4"
    @dragenter.prevent="onDragEnter" @dragover.prevent @dragleave.prevent="onDragLeave" @drop.prevent="onViewDrop"
  >
    <!-- Full-view drag & drop: import dropped Markdown files as new notes -->
    <div v-show="dragDepth > 0" class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center rounded-xl border-2 border-dashed border-primary-500 bg-primary-500/10">
      <div class="rounded-xl bg-[var(--ll-elevated)] px-6 py-4 text-center shadow-lg">
        <Icon name="upload" :size="32" class="text-primary-500" />
        <div class="mt-1 text-sm font-medium">{{ t('notes.drop_here') }}</div>
      </div>
    </div>
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
        <div class="mt-2 flex items-center gap-1">
          <Select v-model="sortKey" :options="sortOptions" class="flex-1" />
          <button
            class="rounded-lg p-1.5 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10"
            :title="sortDesc ? t('notes.sort_desc') : t('notes.sort_asc')"
            @click="sortDesc = !sortDesc"
          ><Icon :name="sortDesc ? 'arrow_downward' : 'arrow_upward'" :size="16" /></button>
          <button
            class="rounded-lg p-1.5 hover:bg-black/[0.05] dark:hover:bg-white/10"
            :class="picking ? 'text-primary-500' : 'text-[var(--ll-muted)]'"
            :title="t('notes.select_mode')"
            @click="togglePicking"
          ><Icon name="checklist" :size="16" /></button>
        </div>
      </div>

      <!-- Acting on several notes at once. Kept to what is genuinely a bulk
           action: moving, tagging, pinning and discarding. -->
      <div v-if="picking && !showTrash" class="flex flex-wrap items-center gap-1 border-b border-[var(--ll-border)] bg-primary-500/5 px-3 py-2">
        <span class="text-xs font-medium">{{ t('notes.selected_n', { n: String(selected.length) }) }}</span>
        <button class="ml-auto rounded-md px-1.5 py-0.5 text-xs text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" @click="selectAllVisible">{{ t('notes.select_all') }}</button>
        <div class="flex w-full items-center gap-1 pt-1">
          <Btn variant="ghost" size="xs" icon="drive_file_move" :disabled="!selected.length || bulkBusy" @click="moveOpen = true">{{ t('notes.move_to') }}</Btn>
          <Btn variant="ghost" size="xs" icon="sell" :disabled="!selected.length || bulkBusy" @click="bulkTag">{{ t('notes.add_tag') }}</Btn>
          <Btn variant="ghost" size="xs" icon="push_pin" :disabled="!selected.length || bulkBusy" @click="bulkPin">{{ t('notes.pin') }}</Btn>
          <Btn variant="ghost" size="xs" icon="delete" class="text-red-600" :disabled="!selected.length || bulkBusy" @click="bulkDelete">{{ t('common.delete') }}</Btn>
        </div>
      </div>
      <template v-if="!showTrash">
        <div
          v-for="row in visibleNotes" :key="row.id"
          class="flex w-full cursor-pointer items-start gap-2 border-b border-[var(--ll-border)] px-4 py-3 text-left last:border-0"
          :class="rowClass(row.id)"
          @click="picking ? toggleSelect(row.id) : openNote(row.id)"
        >
          <input
            v-if="picking"
            type="checkbox" class="mt-1 h-4 w-4 shrink-0 rounded border-[var(--ll-border)] accent-[var(--color-primary-500)]"
            :checked="selected.includes(row.id)"
            @click.stop="toggleSelect(row.id)"
          >
          <div class="min-w-0 flex-1">
          <div class="flex items-center gap-1">
            <Icon v-if="row.pinned" name="push_pin" :size="14" class="text-primary-500" />
            <Icon v-if="row.favorite" name="star" :size="14" class="text-amber-500" />
            <span class="truncate text-sm font-medium">{{ row.title || t('notes.untitled') }}</span>
          </div>
          <div class="mt-0.5 truncate text-xs text-[var(--ll-muted)]">{{ fmtDate(row.updated_at) }}</div>
          <div v-if="row.tags.length" class="mt-1 flex flex-wrap gap-1">
            <span v-for="tg in row.tags" :key="tg" class="rounded bg-black/[0.05] px-1.5 text-[10px] text-[var(--ll-muted)] dark:bg-white/10">#{{ tg }}</span>
          </div>
          </div>
        </div>
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

    <Modal v-model="moveOpen" :title="t('notes.move_to')">
      <Select v-model.number="moveTarget" :options="folderOptions" />
      <template #footer>
        <Btn variant="ghost" @click="moveOpen = false">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" @click="applyMove">{{ t('common.save') }}</Btn>
      </template>
    </Modal>

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
        <div v-if="!preview" class="flex flex-wrap items-center gap-0.5 border-b border-[var(--ll-border)] px-2 py-1">
          <button v-for="b in toolbar" :key="b.key" class="rounded-md p-1.5 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('notes.md_' + b.key)" @click="b.run()">
            <Icon :name="b.icon" :size="18" />
          </button>
          <input ref="imgInput" type="file" accept="image/*" class="hidden" @change="onInsertImage">
        </div>
        <div class="flex min-h-0 flex-1">
          <textarea
            v-if="!preview"
            ref="bodyArea"
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

    <!-- Media picker: embed an image/video from Upload, Files or Gallery; link a File. -->
    <Modal v-model="pickerOpen" :title="t(pickerMode === 'link' ? 'notes.link_file' : pickerMode === 'video' ? 'notes.embed_video' : 'notes.embed_image')" width="46rem">
      <div class="flex flex-col gap-3">
        <div class="flex gap-1 border-b border-[var(--ll-border)]">
          <button
            v-for="tab in (pickerMode === 'link' ? (['files'] as const) : (['upload', 'files', 'gallery'] as const))"
            :key="tab" class="px-3 py-2 text-sm"
            :class="pickerTab === tab ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'"
            @click="pickerTab = tab"
          >{{ t('notes.picker_' + tab) }}</button>
        </div>

        <div v-if="pickerTab === 'upload'" class="p-6 text-center">
          <input ref="pickerUpload" type="file" :accept="pickerMode === 'video' ? 'video/*' : 'image/*'" class="hidden" @change="onPickerUpload">
          <Btn icon="upload" @click="pickerUpload?.click()">{{ t('notes.upload') }}</Btn>
        </div>

        <div v-else-if="pickerTab === 'files'" class="max-h-[50vh] overflow-y-auto">
          <div v-if="pickerBusy" class="p-4 text-sm text-[var(--ll-muted)]">…</div>
          <div v-else-if="!pickerFiles.length" class="p-4 text-sm text-[var(--ll-muted)]">{{ t('notes.picker_empty') }}</div>
          <button
            v-for="f in pickerFiles" :key="f.id"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-black/[0.04] dark:hover:bg-white/5"
            @click="pickFrom('file', f)"
          >
            <Icon name="description" :size="18" class="shrink-0 text-[var(--ll-muted)]" /><span class="truncate">{{ f.name }}</span>
          </button>
        </div>

        <div v-else class="grid max-h-[50vh] grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6">
          <div v-if="pickerBusy" class="col-span-full p-4 text-sm text-[var(--ll-muted)]">…</div>
          <div v-else-if="!pickerPhotos.length" class="col-span-full p-4 text-sm text-[var(--ll-muted)]">{{ t('notes.picker_empty') }}</div>
          <button
            v-for="p in pickerPhotos" :key="p.id"
            class="aspect-square overflow-hidden rounded-lg border border-[var(--ll-border)] hover:opacity-80"
            @click="pickFrom('gallery', p)"
          >
            <img :src="galleryThumb(p.id)" class="h-full w-full object-cover" loading="lazy" :alt="p.name" >
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { fmtDateTime } from '@spa/lib/datetime';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, TextField, Select, Icon, Modal } from '@spa/ui';
import { api } from '@spa/api/client';
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
const imgInput = ref<HTMLInputElement | null>(null);
const bodyArea = ref<HTMLTextAreaElement | null>(null);

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

// --- sorting ----------------------------------------------------------------
// Only fields the row actually carries. Offering "oldest first" over a created
// date the list does not have would be a promise the data cannot keep.
const sortKey = ref(localStorage.getItem('ll_notes_sort') || 'updated');
const sortDesc = ref(localStorage.getItem('ll_notes_sort_dir') !== 'asc');

const sortOptions = computed(() => [
  { title: t('notes.sort_updated'), value: 'updated' },
  { title: t('notes.sort_title'), value: 'title' },
  { title: t('notes.sort_pinned'), value: 'pinned' },
]);

watch([sortKey, sortDesc], () => {
  localStorage.setItem('ll_notes_sort', sortKey.value);
  localStorage.setItem('ll_notes_sort_dir', sortDesc.value ? 'desc' : 'asc');
});

const collator = new Intl.Collator(document.documentElement.lang || 'de', { numeric: true, sensitivity: 'base' });

function sortNotes(rows: NoteRow[]): NoteRow[] {
  const dir = sortDesc.value ? -1 : 1;

  return [...rows].sort((a, b) => {
    // Pinned notes stay on top whatever else is chosen -- that is what pinning
    // means, and a sort that buries them would make the pin useless.
    if (a.pinned !== b.pinned) return a.pinned ? -1 : 1;
    if (sortKey.value === 'title') return dir * collator.compare(a.title || '', b.title || '');
    if (sortKey.value === 'pinned') return dir * ((a.favorite ? 1 : 0) - (b.favorite ? 1 : 0));

    return dir * ((a.updated_at || '') < (b.updated_at || '') ? -1 : 1);
  });
}

const visibleNotes = computed(() => {
  const rows = searchHits.value ?? n.notes.filter((r) =>
    (activeFolder.value === null || r.note_folder_id === activeFolder.value)
    && (activeTag.value === null || r.tags.includes(activeTag.value)),
  );

  return sortNotes(rows);
});

// --- selecting several notes -------------------------------------------------
const picking = ref(false);
const selected = ref<number[]>([]);
const bulkBusy = ref(false);

function togglePicking() {
  picking.value = !picking.value;
  if (!picking.value) selected.value = [];
}

function toggleSelect(id: number) {
  const i = selected.value.indexOf(id);
  if (i === -1) selected.value.push(id);
  else selected.value.splice(i, 1);
}

function selectAllVisible() {
  selected.value = visibleNotes.value.map((r) => r.id);
}

function rowClass(id: number): string {
  if (picking.value && selected.value.includes(id)) return 'bg-primary-500/[0.12]';
  if (!picking.value && current.value?.id === id) return 'bg-primary-500/10';

  return 'hover:bg-black/[0.02] dark:hover:bg-white/[0.03]';
}

/**
 * Apply one patch to every selected note.
 *
 * Each call is a partial update: it names only what changes, so the body and
 * everything else the note carries stays where it is.
 */
async function bulkPatch(patch: (row: NoteRow) => Record<string, unknown> | null): Promise<void> {
  if (!selected.value.length || bulkBusy.value) return;
  bulkBusy.value = true;
  try {
    for (const id of [...selected.value]) {
      const row = n.notes.find((r) => r.id === id);
      if (!row) continue;
      const body = patch(row);
      if (body) await n.update(id, body);
    }
    selected.value = [];
    await n.load();
    if (current.value?.id) await openNote(current.value.id);
  } catch {
    error(t('common.error'));
  } finally {
    bulkBusy.value = false;
  }
}

const moveOpen = ref(false);
const moveTarget = ref(0);

async function applyMove(): Promise<void> {
  moveOpen.value = false;
  const target = moveTarget.value || null;
  await bulkPatch(() => ({ note_folder_id: target }));
}

async function bulkTag(): Promise<void> {
  const raw = await promptAsk(t('notes.add_tag_ph'), { value: '' });
  if (raw === null) return;
  const tags = raw.split(',').map((x) => x.trim()).filter(Boolean);
  if (!tags.length) return;
  // Added, never replaced: a bulk tag is not a reason to lose the tags a note
  // already had.
  await bulkPatch((row) => ({ tags: [...new Set([...row.tags, ...tags])] }));
}

async function bulkPin(): Promise<void> {
  // Mixed selections pin rather than unpin: the visible effect is the same for
  // every note picked, which a toggle-each would not be.
  const allPinned = selected.value.every((id) => n.notes.find((r) => r.id === id)?.pinned);
  await bulkPatch(() => ({ pinned: !allPinned }));
}

async function bulkDelete(): Promise<void> {
  if (!await confirmAsk(t('notes.delete_n_confirm', { n: String(selected.value.length) }), { danger: true })) return;
  if (bulkBusy.value) return;
  bulkBusy.value = true;
  try {
    const ids = [...selected.value];
    for (const id of ids) await n.destroy(id);
    selected.value = [];
    if (current.value?.id && ids.includes(current.value.id)) current.value = null;
    await n.load();
  } catch {
    error(t('common.error'));
  } finally {
    bulkBusy.value = false;
  }
}

function notesInFolder(id: number | null) {
  return n.notes.filter((r) => (id === null ? true : r.note_folder_id === id));
}

function fmtDate(iso: string | null) {
  return fmtDateTime(iso);
}

const route = useRoute();
const router = useRouter();
// Deep-open from global search (?open=<id>): open that note once loaded.
watch(() => route.query.open, (v) => { const id = Number(v); if (id) void openNote(id); });

// ---- Deep links: mirror the active folder/tag/open note into the URL query so
// a reload or shared link lands on the same place. `restoring` gates the
// write-watch while we apply an incoming URL.
let restoring = false;
function buildQuery(): Record<string, string> {
  const q: Record<string, string> = {};
  if (activeFolder.value !== null) q.folder = String(activeFolder.value);
  if (activeTag.value !== null) q.tag = activeTag.value;
  if (current.value?.id) q.note = String(current.value.id);
  return q;
}
watch([activeFolder, activeTag, () => current.value?.id], () => {
  if (restoring) return;
  const q = buildQuery();
  const keys = ['folder', 'tag', 'note'];
  const cur: Record<string, string> = {};
  for (const k of keys) { const v = route.query[k]; if (typeof v === 'string') cur[k] = v; }
  if (JSON.stringify(q) !== JSON.stringify(cur)) void router.replace({ query: q });
});
// Apply the URL query on load: restore folder/tag, then open the deep-linked note.
async function applyRoute() {
  restoring = true;
  const q = route.query;
  try {
    activeFolder.value = q.folder ? Number(q.folder) : null;
    activeTag.value = typeof q.tag === 'string' ? q.tag : null;
  } finally { restoring = false; }
  const nid = Number(q.note || q.open);
  if (nid) await openNote(nid);
}
onMounted(async () => {
  await n.load();
  await applyRoute();
});

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

// --- Full-view drag & drop: import Markdown files as new notes -----------
const dragDepth = ref(0);
function hasFiles(e: DragEvent) { return Array.from(e.dataTransfer?.types ?? []).includes('Files'); }
function onDragEnter(e: DragEvent) { if (hasFiles(e)) dragDepth.value++; }
function onDragLeave(e: DragEvent) { if (hasFiles(e)) dragDepth.value = Math.max(0, dragDepth.value - 1); }

// Split an optional YAML frontmatter (title/tags) off the top of a Markdown file
// so our own exports round-trip; otherwise the filename becomes the title.
function parseFrontmatter(text: string, fallbackTitle: string): { title: string; tags: string[]; body: string } {
  let title = fallbackTitle;
  let tags: string[] = [];
  let body = text;
  const m = text.match(/^---\n([\s\S]*?)\n---\n?/);
  if (m) {
    body = text.slice(m[0].length);
    for (const line of m[1].split('\n')) {
      const t2 = line.match(/^title:\s*"?(.*?)"?\s*$/);
      if (t2) title = t2[1] || fallbackTitle;
      const tg = line.match(/^tags:\s*\[(.*)\]\s*$/);
      if (tg) tags = tg[1].split(',').map((x) => x.trim().replace(/^["']|["']$/g, '')).filter(Boolean);
    }
  }
  return { title, tags, body };
}

const isMd = (name: string) => /\.(md|markdown|txt)$/i.test(name);

// Read a dropped FileSystemEntry tree: create a matching note folder per directory
// and a note per Markdown file inside it. Returns the id of the last note created.
async function importEntry(entry: FileSystemEntry, parentFolder: number | null, lastRef: { id: number }): Promise<void> {
  if (entry.isFile) {
    const file = await new Promise<File>((res, rej) => (entry as FileSystemFileEntry).file(res, rej));
    if (!isMd(file.name)) return;
    const base = file.name.replace(/\.(md|markdown|txt)$/i, '');
    const { title, tags, body } = parseFrontmatter(await file.text(), base);
    const note = await n.create({ title, body, tags, note_folder_id: parentFolder });
    lastRef.id = note.id;
  } else if (entry.isDirectory) {
    const folder = await n.createFolder({ name: entry.name, parent_id: parentFolder });
    const reader = (entry as FileSystemDirectoryEntry).createReader();
    // readEntries returns in batches; drain until empty.
    for (;;) {
      const batch = await new Promise<FileSystemEntry[]>((res, rej) => reader.readEntries(res, rej));
      if (!batch.length) break;
      for (const child of batch) await importEntry(child, folder.id, lastRef);
    }
  }
}

async function onViewDrop(e: DragEvent) {
  dragDepth.value = 0;
  // Prefer the entry API so a dropped FOLDER recreates its structure as note
  // folders; fall back to the flat file list (no directory support) otherwise.
  const entries = Array.from(e.dataTransfer?.items ?? [])
    .map((it) => (it.webkitGetAsEntry ? it.webkitGetAsEntry() : null))
    .filter((x): x is FileSystemEntry => x !== null);
  const hasDir = entries.some((x) => x.isDirectory);
  try {
    const lastRef = { id: 0 };
    if (hasDir) {
      for (const entry of entries) await importEntry(entry, activeFolder.value, lastRef);
    } else {
      const files = Array.from(e.dataTransfer?.files ?? []).filter((f) => isMd(f.name) || f.type === 'text/markdown');
      if (!files.length) return;
      for (const f of files) {
        const base = f.name.replace(/\.(md|markdown|txt)$/i, '');
        const { title, tags, body } = parseFrontmatter(await f.text(), base);
        const note = await n.create({ title, body, tags, note_folder_id: activeFolder.value });
        lastRef.id = note.id;
      }
    }
    await n.load();
    if (lastRef.id) await openNote(lastRef.id);
    success(t('common.saved'));
  } catch { error(t('common.error')); }
}

// --- Markdown editor toolbar ---------------------------------------------
// Each button mutates the textarea selection then restores focus/caret.
function edit(fn: (val: string, s: number, e: number) => { text: string; from: number; to: number }) {
  const ta = bodyArea.value;
  if (!ta || !current.value) return;
  const val = ta.value;
  const s = ta.selectionStart ?? val.length;
  const e = ta.selectionEnd ?? val.length;
  const r = fn(val, s, e);
  current.value.body = r.text;
  nextTick(() => { ta.focus(); ta.setSelectionRange(r.from, r.to); });
}
// Wrap the selection with before/after (bold, italic, code, strike).
function wrap(before: string, after: string, ph: string) {
  edit((val, s, e) => {
    const sel = val.slice(s, e) || ph;
    const text = val.slice(0, s) + before + sel + after + val.slice(e);
    const from = s + before.length;
    return { text, from, to: from + sel.length };
  });
}
// Prefix each line of the selection (headings, lists, quote, checkbox).
function linePrefix(prefix: string) {
  edit((val, s, e) => {
    const lineStart = val.lastIndexOf('\n', s - 1) + 1;
    const block = val.slice(lineStart, e);
    const replaced = block.split('\n').map((l) => prefix + l).join('\n');
    const text = val.slice(0, lineStart) + replaced + val.slice(e);
    return { text, from: lineStart, to: lineStart + replaced.length };
  });
}
function insert(snippet: string, caretBack = 0) {
  edit((val, s, e) => {
    const text = val.slice(0, s) + snippet + val.slice(e);
    const pos = s + snippet.length - caretBack;
    return { text, from: pos, to: pos };
  });
}
function insertLink() {
  edit((val, s, e) => {
    const sel = val.slice(s, e) || t('notes.md_link_text');
    const snippet = `[${sel}](url)`;
    const text = val.slice(0, s) + snippet + val.slice(e);
    const from = s + snippet.length - 4; // caret inside (url)
    return { text, from, to: from + 3 };
  });
}
function insertWikilink() {
  edit((val, s, e) => {
    const sel = val.slice(s, e) || t('notes.md_link_text');
    const snippet = `[[${sel}]]`;
    const text = val.slice(0, s) + snippet + val.slice(e);
    const from = s + 2;
    return { text, from, to: from + sel.length };
  });
}
// --- Media picker: embed an image/video from upload, Files or Gallery; link a File.
type PickerMode = 'image' | 'video' | 'link';
interface PickItem { id: number; name: string; mime?: string | null }
const pickerOpen = ref(false);
const pickerMode = ref<PickerMode>('image');
const pickerTab = ref<'upload' | 'files' | 'gallery'>('files');
const pickerFiles = ref<PickItem[]>([]);
const pickerPhotos = ref<Array<PickItem & { media_type?: string }>>([]);
const pickerBusy = ref(false);
const pickerUpload = ref<HTMLInputElement | null>(null);
const galleryThumb = (id: number) => api.streamUrl(`/api/v1/gallery/${id}/thumb`);

async function openPicker(mode: PickerMode) {
  if (!current.value?.id) {
    insert(mode === 'video' ? '\n<video controls src="url"></video>\n' : mode === 'link' ? '[text](url)' : '![alt](url)');
    return;
  }
  pickerMode.value = mode;
  pickerTab.value = 'files';
  pickerOpen.value = true;
  await loadPickerData();
}
async function loadPickerData() {
  pickerBusy.value = true;
  try {
    const isVid = pickerMode.value === 'video';
    const fd = await api.get<{ files?: Array<{ id: number; name: string; mime?: string | null }> }>('/api/v1/files/data').catch(() => ({ files: [] }));
    const all = fd.files ?? [];
    pickerFiles.value = pickerMode.value === 'link'
      ? all
      : all.filter((f) => (f.mime || '').startsWith(isVid ? 'video/' : 'image/'));
    if (pickerMode.value !== 'link') {
      const gd = await api.get<{ photos?: Array<{ id: number; name: string; media_type?: string }> }>('/api/v1/gallery/data').catch(() => ({ photos: [] }));
      pickerPhotos.value = (gd.photos ?? []).filter((p) => (isVid ? p.media_type === 'video' : p.media_type !== 'video'));
    } else { pickerPhotos.value = []; }
  } finally { pickerBusy.value = false; }
}
function insertMedia(mode: PickerMode, name: string, url: string) {
  if (mode === 'video') insert(`\n<video controls src="${url}"></video>\n`);
  else if (mode === 'link') insert(`[${name}](${url})`);
  else insert(`![${name}](${url})`);
}
async function pickFrom(source: 'file' | 'gallery', item: PickItem) {
  if (!current.value?.id) return;
  try {
    if (pickerMode.value === 'link') {
      insertMedia('link', item.name, api.streamUrl(`/api/v1/files/entries/${item.id}/raw`));
    } else {
      const att = await n.attachFrom(current.value.id, source, item.id);
      (current.value.attachments ??= []).push(att);
      insertMedia(pickerMode.value, att.name, n.attachmentUrl(current.value.id, att.id));
    }
    pickerOpen.value = false;
  } catch { error(t('common.error')); }
}
async function onPickerUpload(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0]; input.value = '';
  if (!file || !current.value?.id) return;
  try {
    const att = await n.attach(current.value.id, file);
    (current.value.attachments ??= []).push(att);
    insertMedia(pickerMode.value, att.name, n.attachmentUrl(current.value.id, att.id));
    pickerOpen.value = false;
  } catch { error(t('common.error')); }
}

const toolbar = [
  { key: 'bold', icon: 'format_bold', run: () => wrap('**', '**', t('notes.md_bold_text')) },
  { key: 'italic', icon: 'format_italic', run: () => wrap('*', '*', t('notes.md_italic_text')) },
  { key: 'strike', icon: 'strikethrough_s', run: () => wrap('~~', '~~', '') },
  { key: 'h1', icon: 'format_h1', run: () => linePrefix('# ') },
  { key: 'heading', icon: 'format_h2', run: () => linePrefix('## ') },
  { key: 'h3', icon: 'format_h3', run: () => linePrefix('### ') },
  { key: 'quote', icon: 'format_quote', run: () => linePrefix('> ') },
  { key: 'ulist', icon: 'format_list_bulleted', run: () => linePrefix('- ') },
  { key: 'olist', icon: 'format_list_numbered', run: () => linePrefix('1. ') },
  { key: 'checklist', icon: 'checklist', run: () => linePrefix('- [ ] ') },
  { key: 'code', icon: 'code', run: () => wrap('`', '`', 'code') },
  { key: 'codeblock', icon: 'data_object', run: () => insert('\n```\n\n```\n', 5) },
  { key: 'link', icon: 'link', run: insertLink },
  { key: 'wikilink', icon: 'account_tree', run: insertWikilink },
  { key: 'image', icon: 'image', run: () => openPicker('image') },
  { key: 'video', icon: 'movie', run: () => openPicker('video') },
  { key: 'linkfile', icon: 'attach_file', run: () => openPicker('link') },
  { key: 'table', icon: 'table_chart', run: () => insert('\n| A | B |\n| --- | --- |\n| 1 | 2 |\n') },
  { key: 'rule', icon: 'horizontal_rule', run: () => insert('\n---\n') },
] as const;

// Upload a picked image as an attachment, then insert a Markdown image pointing
// at its (sandboxed) raw URL. Needs a saved note; otherwise the button inserts a
// plain template (see toolbar image run).
async function onInsertImage(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  input.value = '';
  if (!file || !current.value?.id) return;
  try {
    const att = await n.attach(current.value.id, file);
    (current.value.attachments ??= []).push(att);
    insert(`![${att.name}](${n.attachmentUrl(current.value.id, att.id)})`);
  } catch { error(t('common.error')); }
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
