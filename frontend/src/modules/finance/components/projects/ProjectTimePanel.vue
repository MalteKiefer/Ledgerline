<template>
  <Card :title="t('finance-projects.tab_time')">
    <template #header>
      <Btn size="sm" icon="add" data-action="time-add" @click="editing = true">{{ t('finance-projects.time_add') }}</Btn>
    </template>

    <form v-if="editing" class="mb-4 grid gap-2 rounded-lg border border-[var(--ll-border)] p-3 sm:grid-cols-2" @submit.prevent="save">
      <TextField data-field="time-worked-on" type="date" :model-value="form.worked_on" :label="t('finance-projects.time_worked_on')" required @update:model-value="form.worked_on = $event" />
      <TextField data-field="time-hours" :model-value="form.hours" inputmode="decimal" :label="t('finance-projects.time_hours')" required @update:model-value="form.hours = $event" />
      <label class="flex items-center gap-2">
        <input data-field="time-billable" type="checkbox" :checked="form.billable" @change="form.billable = ($event.target as HTMLInputElement).checked">
        <span class="text-sm">{{ t('finance-projects.time_billable') }}</span>
      </label>
      <TextField data-field="time-rate" :model-value="form.hourly_rate_minor ?? ''" inputmode="numeric" :label="t('finance-projects.time_rate')" @update:model-value="form.hourly_rate_minor = $event || null" />
      <div class="flex gap-2 sm:col-span-2">
        <Btn type="submit" size="sm" :loading="detail.actionLoading" data-action="time-save">{{ t('common.save') }}</Btn>
        <Btn type="button" size="sm" variant="ghost" data-action="time-cancel" @click="editing = false">{{ t('common.cancel') }}</Btn>
      </div>
    </form>

    <p v-if="detail.time.loading && !detail.time.data" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="detail.time.error" role="alert" class="text-sm text-red-600">{{ detail.time.error }}</p>
    <p v-else-if="entries.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.time_empty') }}</p>
    <ul v-else class="divide-y divide-[var(--ll-border)]">
      <li v-for="entry in entries" :key="entry.id" class="flex items-center gap-3 py-2" :data-time-entry="entry.id">
        <span class="w-24 text-xs text-[var(--ll-muted)]">{{ entry.worked_on }}</span>
        <span class="flex-1 font-mono tabular-nums">{{ formatScaled(entry.quantity_scaled) }}</span>
        <Badge v-if="entry.invoiced_at" tone="primary">{{ t('finance-projects.time_locked') }}</Badge>
        <template v-else>
          <input type="checkbox" :data-select-time="entry.id" :checked="selected.has(entry.id)" @change="toggle(entry.id, ($event.target as HTMLInputElement).checked)">
          <Btn size="xs" variant="ghost" icon="delete" :title="t('common.delete')" @click="remove(entry)" />
        </template>
      </li>
    </ul>

    <Btn v-if="selected.size > 0" size="sm" data-action="time-invoice-draft" class="mt-3" :loading="detail.actionLoading" @click="createDraft">
      {{ t('finance-projects.time_create_invoice_draft') }}
    </Btn>

    <Pager :page="meta.current_page" :per-page="meta.per_page" :total="meta.total" @update:page="setPage" />
  </Card>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Badge, Btn, Card, Pager, TextField } from '@spa/ui';
import type { TimeEntry, TimeEntryInput } from '@spa/modules/finance/models/project';
import type { InvoiceDraftTarget } from '@spa/modules/finance/models/project';
import { formatScaled } from '@spa/modules/finance/components/projects/format';

const props = defineProps<{ detail: { time: { data: { data: TimeEntry[]; meta: { current_page: number; per_page: number; total: number } } | null; loading: boolean; error: string | null; query: { page: number; per_page: number } }; actionLoading: boolean; loadTime: (id: string) => Promise<void>; createTime: (input: TimeEntryInput) => Promise<TimeEntry>; deleteTime: (id: string, version: number) => Promise<void>; createInvoiceDraft: (ids: string[]) => Promise<InvoiceDraftTarget> }; projectId: string }>();

const entries = computed(() => props.detail.time.data?.data ?? []);
const meta = computed(() => props.detail.time.data?.meta ?? { current_page: 1, per_page: 20, total: 0 });
const editing = ref(false);
const selected = reactive(new Set<string>());
const form = reactive<TimeEntryInput>(empty());

function empty(): TimeEntryInput {
  return { work_item_id: null, worked_on: new Date().toISOString().slice(0, 10), hours: '1.0000', description: null, billable: true, hourly_rate_minor: null, currency: 'EUR' };
}

async function save(): Promise<void> {
  await props.detail.createTime({ ...form });
  editing.value = false;
  Object.assign(form, empty());
}

async function remove(entry: TimeEntry): Promise<void> {
  await props.detail.deleteTime(entry.id, entry.version);
  selected.delete(entry.id);
}

function toggle(id: string, checked: boolean): void {
  if (checked) selected.add(id); else selected.delete(id);
}

async function createDraft(): Promise<void> {
  await props.detail.createInvoiceDraft([...selected]);
  selected.clear();
}

function setPage(page: number): void {
  props.detail.time.query.page = page;
  void props.detail.loadTime(props.projectId);
}

watch(() => props.projectId, (id) => { if (id) void props.detail.loadTime(id); }, { immediate: true });
</script>
