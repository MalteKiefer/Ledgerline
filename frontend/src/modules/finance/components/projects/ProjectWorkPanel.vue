<template>
  <Card :title="t('finance-projects.tab_work')">
    <template #header>
      <Btn size="sm" icon="add" data-action="work-add" @click="startCreate">{{ t('finance-projects.work_add') }}</Btn>
    </template>

    <form v-if="editing" class="mb-4 grid gap-2 rounded-lg border border-[var(--ll-border)] p-3 sm:grid-cols-2" @submit.prevent="save">
      <TextField data-field="work-title" :model-value="form.title" :label="t('finance-projects.work_title')" required @update:model-value="form.title = $event" />
      <label>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('common.status') }}</span>
        <select data-field="work-status" :value="form.status" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="form.status = (($event.target as HTMLSelectElement).value as WorkItemStatus)">
          <option v-for="status in statuses" :key="status" :value="status">{{ t(`finance-projects.work_status_${status}`) }}</option>
        </select>
      </label>
      <label class="flex items-center gap-2">
        <input data-field="work-milestone" type="checkbox" :checked="form.is_milestone" @change="form.is_milestone = ($event.target as HTMLInputElement).checked">
        <span class="text-sm">{{ t('finance-projects.work_milestone') }}</span>
      </label>
      <TextField v-if="!form.is_milestone" data-field="work-estimate" :model-value="form.estimate_hours ?? ''" inputmode="decimal" :label="t('finance-projects.work_estimate_hours')" @update:model-value="form.estimate_hours = $event || null" />
      <div class="flex gap-2 sm:col-span-2">
        <Btn type="submit" size="sm" :loading="detail.actionLoading" data-action="work-save">{{ t('common.save') }}</Btn>
        <Btn type="button" size="sm" variant="ghost" data-action="work-cancel" @click="editing = null">{{ t('common.cancel') }}</Btn>
      </div>
    </form>

    <p v-if="detail.work.loading && !detail.work.data" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="detail.work.error" role="alert" class="text-sm text-red-600">{{ detail.work.error }}</p>
    <p v-else-if="items.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.work_empty') }}</p>
    <ul v-else class="divide-y divide-[var(--ll-border)]">
      <li v-for="item in items" :key="item.id" class="flex items-center gap-3 py-2" :data-work-item="item.id">
        <span class="flex-1 truncate">
          <Icon v-if="item.is_milestone" name="flag" :size="16" class="mr-1 inline text-[var(--ll-muted)]" />
          {{ item.title }}
        </span>
        <span class="text-xs text-[var(--ll-muted)]">{{ t(`finance-projects.work_status_${item.status}`) }}</span>
        <Btn size="xs" variant="ghost" icon="edit" :title="t('common.edit')" @click="startEdit(item)" />
        <Btn size="xs" variant="ghost" icon="delete" :title="t('common.delete')" @click="remove(item)" />
      </li>
    </ul>

    <Pager :page="meta.current_page" :per-page="meta.per_page" :total="meta.total" @update:page="setPage" />
  </Card>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Icon, Pager, TextField } from '@spa/ui';
import type { WorkItem, WorkItemInput, WorkItemStatus } from '@spa/modules/finance/models/project';

const props = defineProps<{ detail: { work: { data: { data: WorkItem[]; meta: { current_page: number; per_page: number; total: number } } | null; loading: boolean; error: string | null; query: { page: number; per_page: number } }; actionLoading: boolean; loadWork: (id: string) => Promise<void>; createWork: (input: WorkItemInput) => Promise<WorkItem>; updateWork: (id: string, input: WorkItemInput & { version: number }) => Promise<WorkItem>; deleteWork: (id: string, version: number) => Promise<void> }; projectId: string }>();

const statuses: WorkItemStatus[] = ['open', 'in_progress', 'done'];
const items = computed(() => props.detail.work.data?.data ?? []);
const meta = computed(() => props.detail.work.data?.meta ?? { current_page: 1, per_page: 20, total: 0 });
const editing = ref<WorkItem | null>(null);
const form = reactive<WorkItemInput>(empty());

function empty(): WorkItemInput {
  return { title: '', status: 'open', is_milestone: false, estimate_hours: null, description: null, starts_on: null, due_on: null };
}

function startCreate(): void {
  editing.value = { id: '', resource_type: 'work_item' } as WorkItem;
  Object.assign(form, empty());
}

function startEdit(item: WorkItem): void {
  editing.value = item;
  Object.assign(form, { title: item.title, status: item.status, is_milestone: item.is_milestone, estimate_hours: item.estimate_quantity_scaled, description: item.description, starts_on: item.starts_on, due_on: item.due_on, version: item.version });
}

async function save(): Promise<void> {
  if (!editing.value) return;
  if (editing.value.id) await props.detail.updateWork(editing.value.id, { ...form, version: editing.value.version });
  else await props.detail.createWork(form);
  editing.value = null;
}

async function remove(item: WorkItem): Promise<void> {
  await props.detail.deleteWork(item.id, item.version);
}

function setPage(page: number): void {
  props.detail.work.query.page = page;
  void props.detail.loadWork(props.projectId);
}

watch(() => props.projectId, (id) => { if (id) void props.detail.loadWork(id); }, { immediate: true });
</script>
