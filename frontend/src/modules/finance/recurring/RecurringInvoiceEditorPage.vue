<template>
  <section class="space-y-4" aria-labelledby="recurring-edit-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="recurring-edit-heading" class="text-xl font-bold">{{ isNew ? t('invoices.recurring_add') : t('common.edit') }}</h1>
      <div class="flex flex-wrap gap-2">
        <Btn v-if="isNew" :loading="store.actionLoading" data-action="save" @click="save">{{ t('common.save') }}</Btn>
        <template v-else>
          <Btn :loading="store.actionLoading" data-action="add-version" @click="addVersion">{{ t('invoices.recurring_add_version') }}</Btn>
          <Btn v-if="store.current?.status === 'active'" :loading="store.actionLoading" variant="outline" data-action="pause" @click="pause">
            {{ t('invoices.recurring_pause') }}
          </Btn>
          <Btn v-if="store.current?.status === 'paused'" :loading="store.actionLoading" variant="outline" data-action="resume" @click="resume">
            {{ t('invoices.recurring_resume') }}
          </Btn>
          <Btn tag="router-link" :to="{ name: 'finance.recurring-invoices.runs', params: { template: id } }" variant="ghost">
            {{ t('invoices.recurring_runs') }}
          </Btn>
        </template>
      </div>
    </header>

    <div v-if="conflict" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">
      <p>{{ t('invoices.recurring_conflict') }}</p>
      <Btn data-action="load-conflict" variant="outline" size="sm" class="mt-2" @click="loadConflict">{{ t('invoices.quote_conflict_load') }}</Btn>
    </div>
    <p v-else-if="store.actionError" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700">
      {{ errorLabel(store.actionError) }}
    </p>

    <Card :title="t('invoices.recurring_schedule')">
      <div class="grid gap-3 sm:grid-cols-3">
        <label>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.recurring_mode') }}</span>
          <select :disabled="!isNew" :value="schedule.mode" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="schedule.mode = ($event.target as HTMLSelectElement).value as RecurringMode">
            <option v-for="mode in modes" :key="mode" :value="mode">{{ t(`invoices.recurring_mode_${mode}`) }}</option>
          </select>
        </label>
        <label>
          <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.recurring_interval') }}</span>
          <select :disabled="!isNew" :value="schedule.interval" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="schedule.interval = ($event.target as HTMLSelectElement).value as RecurringInterval">
            <option v-for="interval in intervals" :key="interval" :value="interval">{{ t(`invoices.recurring_interval_${interval}`) }}</option>
          </select>
        </label>
        <TextField v-model="schedule.timezone" :disabled="!isNew" :label="t('invoices.recurring_timezone')" />
        <TextField v-model="schedule.start_date" type="date" :disabled="!isNew" :label="t('invoices.recurring_start_date')" />
        <TextField :model-value="schedule.end_date ?? ''" type="date" :label="t('invoices.recurring_end_date')" @update:model-value="schedule.end_date = $event || null" />
        <TextField v-model="schedule.run_time" :disabled="!isNew" :label="t('invoices.recurring_run_time')" />
      </div>
    </Card>

    <Card :title="isNew ? t('invoices.positions') : t('invoices.recurring_new_version')">
      <TextField v-if="!isNew" v-model="effectiveFrom" type="date" :label="t('invoices.recurring_effective_from')" class="mb-3 max-w-xs" />
      <div class="grid gap-3 sm:grid-cols-2">
        <TextField v-model="draft.customer.name" :label="t('invoices.customer')" />
        <TextField :model-value="draft.customer.email ?? ''" type="email" :label="t('common.email')" @update:model-value="draft.customer.email = $event || null" />
        <TextField v-model="draft.issue_date" type="date" :label="t('common.date')" />
        <TextField v-model="draft.due_date" type="date" :label="t('invoices.due_date')" />
        <TextField v-model="draft.currency" :label="t('invoices.currency')" />
      </div>
      <InvoiceLineEditor v-model="draft.lines" class="mt-3" />
    </Card>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { Btn, Card, TextField } from '@spa/ui';
import InvoiceLineEditor from '@spa/modules/finance/components/InvoiceLineEditor.vue';
import type { InvoiceDraftInput } from '@spa/modules/finance/models/invoice';
import type { RecurringInterval, RecurringInvoiceTemplateInput, RecurringMode } from '@spa/modules/finance/models/recurring';
import { useRecurringStore } from '@spa/modules/finance/stores/recurring';

const route = useRoute();
const router = useRouter();
const store = useRecurringStore();
const id = computed(() => typeof route.params.template === 'string' ? route.params.template : null);
const isNew = computed(() => id.value === null);
const conflict = ref(false);
const modes: RecurringMode[] = ['draft', 'auto_send'];
const intervals: RecurringInterval[] = ['monthly', 'quarterly', 'semiannual', 'annual'];

const schedule = reactive({
  mode: 'draft' as RecurringMode,
  interval: 'monthly' as RecurringInterval,
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
  start_date: new Date().toISOString().slice(0, 10),
  end_date: null as string | null,
  run_time: '08:00:00',
});
const effectiveFrom = ref(new Date().toISOString().slice(0, 10));
const draft = reactive<InvoiceDraftInput>(emptyDraft());

onMounted(async () => {
  if (id.value !== null) await store.loadTemplate(id.value).catch(() => undefined);
});

function emptyDraft(): InvoiceDraftInput {
  const today = new Date().toISOString().slice(0, 10);

  return {
    issue_date: today,
    due_date: today,
    currency: 'EUR',
    customer: { name: '', email: null },
    partner_id: null,
    project_id: null,
    lines: [{ description: '', quantity: '1.0000', unit: 'month', unit_price: '0.00', tax_rate: '19.00', kind: 'service', product_id: null }],
    discount_type: 'none',
    discount_value: null,
  };
}

async function save(): Promise<void> {
  conflict.value = false;
  try {
    const input: RecurringInvoiceTemplateInput = { ...schedule, draft: { ...draft } };
    const created = await store.create(input, globalThis.crypto.randomUUID());
    await router.push({ name: 'finance.recurring-invoices.edit', params: { template: created.id } });
  } catch (error) {
    conflict.value = store.actionError === 'version_conflict' || store.actionError === 'recurring_template_version_conflict';
    throw error;
  }
}

async function addVersion(): Promise<void> {
  if (id.value === null || !store.current) return;
  conflict.value = false;
  try {
    await store.addVersion(id.value, {
      effective_from: effectiveFrom.value,
      expected_version: store.current.version,
      draft: { ...draft },
    }, globalThis.crypto.randomUUID());
  } catch {
    conflict.value = store.actionError === 'recurring_template_version_conflict';
  }
}

async function pause(): Promise<void> {
  if (id.value === null || !store.current) return;
  await store.pause(id.value, store.current.version, globalThis.crypto.randomUUID()).catch(() => undefined);
}

async function resume(): Promise<void> {
  if (id.value === null || !store.current) return;
  await store.resume(id.value, store.current.version, globalThis.crypto.randomUUID()).catch(() => undefined);
}

function loadConflict(): void {
  conflict.value = false;
}

function errorLabel(code: string): string {
  return `${t('invoices.recurring_error')} (${code})`;
}
</script>
