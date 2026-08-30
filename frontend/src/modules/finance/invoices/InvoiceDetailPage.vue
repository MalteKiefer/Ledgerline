<template>
  <section class="space-y-4" aria-labelledby="invoice-detail-heading">
    <p v-if="store.detailLoading || !invoice" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs text-[var(--ll-muted)]">{{ invoice.number ?? t('invoices.invoice_status_draft') }}</p>
          <h1 id="invoice-detail-heading" class="text-xl font-bold">{{ customerName }}</h1>
        </div>
        <AsyncStateBadge :tone="statusTone(invoice.status)">{{ t(`invoices.invoice_status_${invoice.status}`) }}</AsyncStateBadge>
      </header>

      <p v-if="outcome" class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3 text-sm" role="status">{{ outcome }}</p>
      <p v-if="store.actionError" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700" role="alert">
        {{ errorLabel(store.actionError) }}
      </p>

      <div class="flex flex-wrap gap-2">
        <Btn v-if="invoice.status === 'draft'" tag="router-link" :to="{ name: 'finance.invoices.edit', params: { invoice: invoice.id } }" variant="outline">
          {{ t('common.edit') }}
        </Btn>
        <Btn v-if="invoice.status === 'draft'" :loading="store.actionLoading" data-action="finalize" @click="finalize">
          {{ t('invoices.invoice_finalize') }}
        </Btn>
        <Btn v-if="canDeliver" :loading="store.actionLoading" data-action="deliver" variant="outline" @click="deliver">
          {{ t('invoices.invoice_send') }}
        </Btn>
        <Btn v-if="canRemind" :loading="store.actionLoading" data-action="remind" variant="outline" @click="remind">
          {{ t('invoices.invoice_remind') }}
        </Btn>
        <Btn v-if="canCancel" :loading="store.actionLoading" data-action="cancel" variant="danger" @click="requestCancel">
          {{ t('invoices.invoice_cancel') }}
        </Btn>
      </div>

      <p v-if="confirmingCancel" class="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm" role="alert">
        {{ t('invoices.invoice_cancel_confirm') }}
        <Btn data-action="cancel-confirm" size="sm" variant="danger" class="ml-2" @click="cancel">{{ t('common.confirm') }}</Btn>
        <Btn size="sm" variant="ghost" class="ml-2" @click="confirmingCancel = false">{{ t('common.cancel') }}</Btn>
      </p>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <Card :title="t('invoices.revision_history')">
          <InvoiceActivity :revisions="store.revisions" :deliveries="deliveries" />
        </Card>
        <Card :title="t('invoices.gross')">
          <InvoiceTotals :totals="invoice.totals" :allocated-minor="invoice.allocated_minor" :open-minor="invoice.open_minor" />
        </Card>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute } from 'vue-router';
import { Btn, Card } from '@spa/ui';
import AsyncStateBadge from '@spa/modules/finance/components/AsyncStateBadge.vue';
import InvoiceActivity from '@spa/modules/finance/components/InvoiceActivity.vue';
import InvoiceTotals from '@spa/modules/finance/components/InvoiceTotals.vue';
import type { InvoiceDelivery, InvoiceStatus } from '@spa/modules/finance/models/invoice';
import { useInvoicesStore } from '@spa/modules/finance/stores/invoices';

const route = useRoute();
const store = useInvoicesStore();
const id = computed(() => String(route.params.invoice));
const invoice = computed(() => store.current?.id === id.value ? store.current : null);
const customerName = computed(() => {
  const customer = invoice.value?.snapshot.customer as { name?: string } | undefined;

  return customer?.name ?? invoice.value?.number ?? id.value;
});
const outcome = ref<string | null>(null);
const confirmingCancel = ref(false);
const deliveries = ref<InvoiceDelivery[]>([]);

const canDeliver = computed(() => invoice.value !== null && ['finalized', 'sent', 'partially_paid', 'paid'].includes(invoice.value.status));
const canRemind = computed(() => invoice.value !== null && ['sent', 'partially_paid'].includes(invoice.value.status));
const canCancel = computed(() => invoice.value !== null && ['finalized', 'sent', 'partially_paid', 'paid'].includes(invoice.value.status) && invoice.value.kind === 'invoice');

onMounted(async () => {
  await Promise.all([
    store.loadInvoice(id.value),
    store.loadRevisions(id.value),
  ]).catch(() => undefined);
});

async function refreshRevisions(): Promise<void> {
  await store.loadRevisions(id.value).catch(() => undefined);
}

async function finalize(): Promise<void> {
  await guarded(async () => {
    await store.finalize(id.value, globalThis.crypto.randomUUID());
    outcome.value = t('invoices.invoice_finalized');
    await refreshRevisions();
  });
}

async function deliver(): Promise<void> {
  await guarded(async () => {
    const delivery = await store.deliver(id.value, null, globalThis.crypto.randomUUID());
    deliveries.value = [delivery, ...deliveries.value];
    outcome.value = t('invoices.invoice_delivery_queued');
  });
}

async function remind(): Promise<void> {
  await guarded(async () => {
    const delivery = await store.remind(id.value, 1, null, globalThis.crypto.randomUUID());
    deliveries.value = [delivery, ...deliveries.value];
    outcome.value = t('invoices.invoice_reminder_queued');
  });
}

function requestCancel(): void {
  confirmingCancel.value = true;
}

async function cancel(): Promise<void> {
  confirmingCancel.value = false;
  await guarded(async () => {
    const credit = await store.cancel(id.value, globalThis.crypto.randomUUID());
    outcome.value = t('invoices.invoice_cancelled', { number: credit.number ?? credit.id });
    await store.loadInvoice(id.value);
  });
}

async function guarded(operation: () => Promise<void>): Promise<void> {
  try {
    await operation();
  } catch {
    // The typed store error is rendered as the workflow result.
  }
}

function statusTone(status: InvoiceStatus): 'gray' | 'info' | 'success' | 'error' | 'warning' | 'primary' {
  return ({
    draft: 'gray',
    finalized: 'info',
    sent: 'primary',
    partially_paid: 'warning',
    paid: 'success',
    cancelled: 'error',
  } as const)[status];
}

function errorLabel(code: string): string {
  return `${t('invoices.invoice_error')} (${code})`;
}
</script>
