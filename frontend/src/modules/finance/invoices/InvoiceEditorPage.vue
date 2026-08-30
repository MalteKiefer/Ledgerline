<template>
  <section class="space-y-4" aria-labelledby="invoice-edit-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="invoice-edit-heading" class="text-xl font-bold">{{ isNew ? t('invoices.new') : t('common.edit') }}</h1>
      <Btn :loading="store.actionLoading" data-action="save" @click="() => save().catch(() => undefined)">{{ t('common.save') }}</Btn>
    </header>

    <div v-if="conflict" class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">
      <p>{{ t('invoices.invoice_conflict') }}</p>
      <Btn data-action="load-conflict" variant="outline" size="sm" class="mt-2" @click="loadConflict">
        {{ t('invoices.quote_conflict_load') }}
      </Btn>
    </div>
    <p v-else-if="store.actionError" role="alert" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700">
      {{ errorLabel(store.actionError) }}
    </p>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div class="space-y-4">
        <Card :title="t('invoices.customer')">
          <div class="grid gap-3 sm:grid-cols-2">
            <TextField data-field="customer-name" :model-value="form.customer.name" :label="t('invoices.customer')" @update:model-value="setCustomer('name', $event)" />
            <TextField :model-value="form.customer.email ?? ''" type="email" :label="t('common.email')" @update:model-value="setCustomer('email', $event || null)" />
            <TextField :model-value="form.issue_date" type="date" :label="t('common.date')" @update:model-value="set('issue_date', $event)" />
            <TextField :model-value="form.due_date" type="date" :label="t('invoices.due_date')" @update:model-value="set('due_date', $event)" />
            <TextField :model-value="form.currency" :label="t('invoices.currency')" @update:model-value="set('currency', $event.toUpperCase())" />
          </div>
        </Card>
        <Card :title="t('invoices.positions')">
          <InvoiceLineEditor :model-value="form.lines" @update:model-value="set('lines', $event)" />
        </Card>
        <Card :title="t('invoices.discount')">
          <div class="grid gap-3 sm:grid-cols-2">
            <label>
              <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.discount') }}</span>
              <select :value="form.discount_type" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="set('discount_type', ($event.target as HTMLSelectElement).value as InvoiceDiscountType)">
                <option value="none">{{ t('common.none') }}</option>
                <option value="percent">%</option>
                <option value="fixed">{{ t('invoices.discount_amount') }}</option>
              </select>
            </label>
            <TextField v-if="form.discount_type !== 'none'" :model-value="form.discount_value ?? ''" inputmode="decimal" :label="t('invoices.discount_amount')" @update:model-value="set('discount_value', $event || null)" />
          </div>
        </Card>
      </div>

      <Card :title="t('invoices.gross')" class="self-start xl:sticky xl:top-4">
        <InvoiceTotals v-if="store.current" :totals="store.current.totals" />
        <p v-else class="text-sm text-[var(--ll-muted)]">{{ t('invoices.invoice_totals_after_save') }}</p>
      </Card>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { Btn, Card, TextField } from '@spa/ui';
import InvoiceLineEditor from '@spa/modules/finance/components/InvoiceLineEditor.vue';
import InvoiceTotals from '@spa/modules/finance/components/InvoiceTotals.vue';
import type { Invoice, InvoiceDiscountType, InvoiceDraftInput } from '@spa/modules/finance/models/invoice';
import { useInvoicesStore } from '@spa/modules/finance/stores/invoices';

const route = useRoute();
const router = useRouter();
const store = useInvoicesStore();
const id = computed(() => typeof route.params.invoice === 'string' ? route.params.invoice : null);
const isNew = computed(() => id.value === null);
const form = ref<InvoiceDraftInput>(emptyDraft());
const conflict = ref(false);

onMounted(async () => {
  if (id.value !== null) {
    const loaded = await store.loadInvoice(id.value).catch(() => null);
    if (loaded) form.value = inputFrom(loaded);
  }
});

function set<K extends keyof InvoiceDraftInput>(key: K, value: InvoiceDraftInput[K]): void {
  form.value = { ...form.value, [key]: value };
}

function setCustomer(key: 'name' | 'email', value: string | null): void {
  form.value = { ...form.value, customer: { ...form.value.customer, [key]: value } };
}

async function save(): Promise<Invoice> {
  conflict.value = false;
  try {
    const saved = id.value === null
      ? await store.create(form.value)
      : await store.update(id.value, store.current?.version ?? 0, form.value);
    if (id.value === null) await router.push({ name: 'finance.invoices.edit', params: { invoice: saved.id } });

    return saved;
  } catch (error) {
    conflict.value = store.actionError === 'version_conflict' || store.actionError === 'invoice_version_conflict';
    throw error;
  }
}

function loadConflict(): void {
  if (! store.current) return;
  form.value = inputFrom(store.current);
  conflict.value = false;
}

function inputFrom(invoice: Invoice): InvoiceDraftInput {
  const snapshot = invoice.snapshot as {
    customer?: { name?: string; email?: string | null };
    lines?: InvoiceDraftInput['lines'];
    discount?: { type?: InvoiceDiscountType; value?: string | null };
  };

  return {
    issue_date: invoice.issue_date,
    due_date: invoice.due_date,
    currency: invoice.totals.currency,
    customer: { name: snapshot.customer?.name ?? '', email: snapshot.customer?.email ?? null },
    partner_id: invoice.partner_id,
    project_id: invoice.project_id,
    lines: snapshot.lines ?? emptyDraft().lines,
    discount_type: snapshot.discount?.type ?? 'none',
    discount_value: snapshot.discount?.value ?? null,
  };
}

function emptyDraft(): InvoiceDraftInput {
  const today = new Date().toISOString().slice(0, 10);

  return {
    issue_date: today,
    due_date: today,
    currency: 'EUR',
    customer: { name: '', email: null },
    partner_id: null,
    project_id: null,
    lines: [{ description: '', quantity: '1.0000', unit: 'pc', unit_price: '0.00', tax_rate: '19.00', kind: 'service', product_id: null }],
    discount_type: 'none',
    discount_value: null,
  };
}

function errorLabel(code: string): string {
  return `${t('invoices.invoice_error')} (${code})`;
}
</script>
