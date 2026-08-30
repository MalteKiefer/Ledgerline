<template>
  <Card :title="t('finance-projects.tab_notes')">
    <form class="mb-4 grid gap-2 rounded-lg border border-[var(--ll-border)] p-3 sm:grid-cols-2" @submit.prevent="save">
      <label>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('finance-projects.notes_type') }}</span>
        <select data-field="note-type" :value="form.type" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="form.type = (($event.target as HTMLSelectElement).value as NoteType)">
          <option v-for="type in types" :key="type" :value="type">{{ t(`finance-projects.notes_type_${type}`) }}</option>
        </select>
      </label>
      <label>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('finance-projects.notes_visibility') }}</span>
        <select data-field="note-visibility" :value="form.visibility" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="form.visibility = (($event.target as HTMLSelectElement).value as NoteVisibility)">
          <option value="internal">{{ t('finance-projects.notes_visibility_internal') }}</option>
          <option value="customer">{{ t('finance-projects.notes_visibility_customer') }}</option>
        </select>
      </label>
      <textarea
        data-field="note-body"
        :value="form.body"
        class="min-h-20 w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm sm:col-span-2"
        :placeholder="t('finance-projects.notes_body_placeholder')"
        required
        @input="form.body = ($event.target as HTMLTextAreaElement).value"
      />
      <p v-if="form.supersedes_note_id" class="text-xs text-[var(--ll-muted)] sm:col-span-2">
        {{ t('finance-projects.notes_correction_of') }} #{{ form.supersedes_note_id }}
        <button type="button" class="underline" data-action="note-correction-clear" @click="form.supersedes_note_id = null">{{ t('common.cancel') }}</button>
      </p>
      <Btn type="submit" size="sm" class="sm:col-span-2" data-action="note-save">{{ t('common.save') }}</Btn>
    </form>

    <p v-if="detail.notes.loading && !detail.notes.data" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="detail.notes.error" role="alert" class="text-sm text-red-600">{{ detail.notes.error }}</p>
    <p v-else-if="items.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.notes_empty') }}</p>
    <ul v-else class="space-y-3">
      <li v-for="note in items" :key="note.id" class="rounded-lg border border-[var(--ll-border)] p-3 text-sm" :data-note="note.id">
        <div class="mb-1 flex items-center gap-2 text-xs text-[var(--ll-muted)]">
          <span>{{ t(`finance-projects.notes_type_${note.type}`) }}</span>
          <span>·</span>
          <span>{{ note.occurred_at }}</span>
          <span v-if="note.supersedes_note_id" class="text-amber-600">· {{ t('finance-projects.notes_correction_of') }} #{{ note.supersedes_note_id }}</span>
          <button type="button" class="ml-auto underline" data-action="note-correct" @click="startCorrection(note)">{{ t('finance-projects.notes_correct') }}</button>
        </div>
        <p class="whitespace-pre-wrap">{{ note.body }}</p>
      </li>
    </ul>

    <Pager :page="meta.current_page" :per-page="meta.per_page" :total="meta.total" @update:page="setPage" />
  </Card>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Pager } from '@spa/ui';
import type { HistoryItem, NoteInput, NoteType, NoteVisibility } from '@spa/modules/finance/models/history';

const props = defineProps<{ detail: { notes: { data: { data: HistoryItem[]; meta: { current_page: number; per_page: number; total: number } } | null; loading: boolean; error: string | null; query: { page: number; per_page: number } }; loadNotes: (id: string) => Promise<void>; appendNote: (input: NoteInput) => Promise<HistoryItem> }; projectId: string }>();

const types: NoteType[] = ['note', 'decision', 'call', 'email', 'meeting', 'correction'];
const items = computed(() => props.detail.notes.data?.data ?? []);
const meta = computed(() => props.detail.notes.data?.meta ?? { current_page: 1, per_page: 20, total: 0 });
const form = reactive<NoteInput>(empty());

function empty(): NoteInput {
  return { type: 'note', visibility: 'internal', body: '', supersedes_note_id: null };
}

function startCorrection(note: HistoryItem): void {
  Object.assign(form, { type: 'correction', visibility: note.visibility ?? 'internal', body: note.body ?? '', supersedes_note_id: note.id });
}

async function save(): Promise<void> {
  await props.detail.appendNote({ ...form });
  Object.assign(form, empty());
}

function setPage(page: number): void {
  props.detail.notes.query.page = page;
  void props.detail.loadNotes(props.projectId);
}

watch(() => props.projectId, (id) => { if (id) void props.detail.loadNotes(id); }, { immediate: true });
</script>
