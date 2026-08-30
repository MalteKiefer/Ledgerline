<template>
  <Card :title="t('finance-projects.tab_ledger')">
    <template #header>
      <Btn size="sm" icon="add" data-action="ledger-add" @click="editing = true">{{ t('finance-projects.ledger_add') }}</Btn>
    </template>

    <form v-if="editing" class="mb-4 grid gap-2 rounded-lg border border-[var(--ll-border)] p-3 sm:grid-cols-2" @submit.prevent="save">
      <label>
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('finance-projects.ledger_direction') }}</span>
        <select data-field="ledger-direction" :value="form.direction" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="form.direction = (($event.target as HTMLSelectElement).value as LedgerDirection)">
          <option value="out">{{ t('finance-projects.ledger_direction_out') }}</option>
          <option value="in">{{ t('finance-projects.ledger_direction_in') }}</option>
        </select>
      </label>
      <TextField data-field="ledger-amount" :model-value="form.amount_minor" inputmode="numeric" :label="t('finance-projects.ledger_amount')" required @update:model-value="form.amount_minor = $event" />
      <TextField data-field="ledger-title" :model-value="form.title ?? ''" :label="t('finance-projects.ledger_title')" @update:model-value="form.title = $event || null" />
      <TextField data-field="ledger-occurred" type="date" :model-value="form.occurred_on ?? ''" :label="t('common.date')" @update:model-value="form.occurred_on = $event || null" />
      <div class="flex gap-2 sm:col-span-2">
        <Btn type="submit" size="sm" :loading="detail.actionLoading" data-action="ledger-save">{{ t('common.save') }}</Btn>
        <Btn type="button" size="sm" variant="ghost" data-action="ledger-cancel" @click="editing = false">{{ t('common.cancel') }}</Btn>
      </div>
    </form>

    <p v-if="detail.ledger.loading && !detail.ledger.data" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <p v-else-if="detail.ledger.error" role="alert" class="text-sm text-red-600">{{ detail.ledger.error }}</p>
    <p v-else-if="rows.length === 0" class="text-sm text-[var(--ll-muted)]">{{ t('finance-projects.ledger_empty') }}</p>
    <ul v-else class="divide-y divide-[var(--ll-border)]">
      <li v-for="row in rows" :key="row.id" class="flex items-center gap-3 py-2" :data-ledger-entry="row.id">
        <span class="text-xs text-[var(--ll-muted)]">{{ t(`finance-projects.ledger_direction_${row.direction}`) }}</span>
        <span class="flex-1 truncate">{{ row.title ?? '—' }}</span>
        <span class="font-mono tabular-nums" :class="row.direction === 'out' ? 'text-red-600' : 'text-green-600'">{{ formatMinor(row.amount_minor, row.currency) }}</span>
        <Btn size="xs" variant="ghost" icon="delete" :title="t('common.delete')" @click="remove(row)" />
      </li>
    </ul>

    <Pager :page="meta.current_page" :per-page="meta.per_page" :total="meta.total" @update:page="setPage" />
  </Card>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Btn, Card, Pager, TextField } from '@spa/ui';
import type { LedgerDirection, LedgerEntry, LedgerEntryInput } from '@spa/modules/finance/models/project';
import { formatMinor } from '@spa/modules/finance/components/projects/format';

const props = defineProps<{ detail: { ledger: { data: { data: LedgerEntry[]; meta: { current_page: number; per_page: number; total: number } } | null; loading: boolean; error: string | null; query: { page: number; per_page: number } }; actionLoading: boolean; loadLedger: (id: string) => Promise<void>; createLedger: (input: LedgerEntryInput) => Promise<LedgerEntry>; deleteLedger: (id: string, version: number) => Promise<void> }; projectId: string; currency: string }>();

const rows = computed(() => props.detail.ledger.data?.data ?? []);
const meta = computed(() => props.detail.ledger.data?.meta ?? { current_page: 1, per_page: 20, total: 0 });
const editing = ref(false);
const form = reactive<LedgerEntryInput>(empty());

function empty(): LedgerEntryInput {
  return { direction: 'out', amount_minor: '0', currency: props.currency, occurred_on: new Date().toISOString().slice(0, 10), title: null, note: null, category_reference: null, payment_method_reference: null };
}

async function save(): Promise<void> {
  await props.detail.createLedger({ ...form, currency: props.currency });
  editing.value = false;
  Object.assign(form, empty());
}

async function remove(row: LedgerEntry): Promise<void> {
  await props.detail.deleteLedger(row.id, row.version);
}

function setPage(page: number): void {
  props.detail.ledger.query.page = page;
  void props.detail.loadLedger(props.projectId);
}

watch(() => props.projectId, (id) => { if (id) void props.detail.loadLedger(id); }, { immediate: true });
</script>
