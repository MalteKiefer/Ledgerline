<template>
  <section class="space-y-4" aria-labelledby="payment-list-heading">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <h1 id="payment-list-heading" class="text-xl font-bold">{{ t('invoices.payments_title') }}</h1>
      <Btn data-action="record" icon="add" @click="showRecord = true">{{ t('invoices.payment_record') }}</Btn>
    </header>

    <Card body-class="p-0">
      <template #header>
        <TextField
          :model-value="filters.q"
          type="search"
          icon="search"
          :placeholder="t('invoices.payment_search')"
          class="w-full sm:w-72"
          @update:model-value="update({ q: $event })"
        />
        <label class="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            data-filter="unallocated"
            :checked="filters.unallocated === true"
            @change="update({ unallocated: ($event.target as HTMLInputElement).checked ? true : null })"
          >
          {{ t('invoices.payment_unallocated_only') }}
        </label>
      </template>

      <p v-if="store.listLoading" class="p-5 text-sm text-[var(--ll-muted)]" role="status">{{ t('common.loading') }}</p>
      <p v-else-if="store.listError" class="p-5 text-sm text-red-600" role="alert">{{ errorLabel(store.listError) }}</p>
      <p v-else-if="store.items.length === 0" class="p-5 text-sm text-[var(--ll-muted)]">{{ t('invoices.payments_empty') }}</p>
      <div v-else class="divide-y divide-[var(--ll-border)]">
        <RouterLink
          v-for="payment in store.items"
          :key="payment.id"
          :to="{ name: 'finance.payments.show', params: { payment: payment.id } }"
          class="grid gap-3 p-4 hover:bg-black/[0.02] sm:grid-cols-[minmax(0,1fr)_auto_auto] dark:hover:bg-white/5"
        >
          <div class="min-w-0">
            <p class="truncate font-medium">{{ payment.reference ?? payment.counterparty ?? payment.id }}</p>
            <p class="text-xs text-[var(--ll-muted)]">{{ new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(payment.received_at)) }}</p>
          </div>
          <span class="font-mono tabular-nums text-sm">{{ formatMinor(payment.amount_minor, payment.currency) }}</span>
          <span class="font-mono tabular-nums text-sm" :class="payment.unapplied_minor === '0' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
            {{ formatMinor(payment.unapplied_minor, payment.currency) }}
          </span>
        </RouterLink>
      </div>

      <Pager
        :page="store.page.meta.current_page"
        :per-page="store.page.meta.per_page"
        :total="store.page.meta.total"
        @update:page="setPage"
      />
    </Card>

    <Modal v-model="showRecord" :title="t('invoices.payment_record')">
      <div class="grid gap-3">
        <TextField v-model="recordForm.amount" inputmode="decimal" :label="t('invoices.allocation_amount')" />
        <TextField v-model="recordForm.currency" :label="t('invoices.currency')" />
        <TextField v-model="recordForm.received_at" type="datetime-local" :label="t('invoices.payment_received_at')" />
        <TextField v-model="recordForm.reference" :label="t('invoices.payment_reference')" />
        <TextField v-model="recordForm.counterparty" :label="t('invoices.payment_counterparty')" />
      </div>
      <p v-if="recordError" class="mt-2 text-sm text-red-600" role="alert">{{ recordError }}</p>
      <template #footer>
        <Btn variant="ghost" @click="showRecord = false">{{ t('common.cancel') }}</Btn>
        <Btn data-action="record-confirm" :loading="store.actionLoading" @click="record">{{ t('common.save') }}</Btn>
      </template>
    </Modal>
  </section>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRouter } from 'vue-router';
import { Btn, Card, Modal, Pager, TextField } from '@spa/ui';
import { formatMinor } from '@spa/modules/finance/models/money';
import { pageNumber, scalar, boolFlag, useFinanceUrlFilters } from '@spa/modules/finance/composables/useFinanceUrlFilters';
import type { PaymentListFilters, RecordPaymentInput } from '@spa/modules/finance/models/payment';
import { usePaymentsStore } from '@spa/modules/finance/stores/payments';

const store = usePaymentsStore();
const router = useRouter();
const locale = document.documentElement.lang || 'de-DE';
const showRecord = ref(false);
const recordError = ref<string | null>(null);
const recordForm = reactive<RecordPaymentInput>({
  amount: '',
  currency: 'EUR',
  received_at: new Date().toISOString().slice(0, 16),
  reference: null,
  counterparty: null,
  payment_method_id: null,
  source_type: null,
  source_key: null,
});

function parse(query: Record<string, unknown>): Required<Pick<PaymentListFilters, 'q' | 'page'>> & PaymentListFilters {
  return {
    q: scalar(query.q) ?? '',
    unallocated: boolFlag(query.unallocated),
    page: pageNumber(query.page),
  };
}

function serialize(filters: PaymentListFilters & { page: number }): Record<string, string> {
  return {
    ...(filters.q ? { q: filters.q } : {}),
    ...(filters.unallocated === true ? { unallocated: '1' } : {}),
    page: String(filters.page),
  };
}

const { filters, update, setPage } = useFinanceUrlFilters(parse, serialize, ['q', 'unallocated', 'page']);

watch(filters, (value) => {
  void store.loadList(value).catch(() => undefined);
}, { deep: true, immediate: true });

async function record(): Promise<void> {
  recordError.value = null;
  try {
    const input: RecordPaymentInput = {
      ...recordForm,
      received_at: new Date(recordForm.received_at).toISOString(),
    };
    const payment = await store.record(input, globalThis.crypto.randomUUID());
    showRecord.value = false;
    await router.push({ name: 'finance.payments.show', params: { payment: payment.id } });
  } catch {
    recordError.value = `${t('invoices.payment_error')} (${store.actionError ?? 'request_failed'})`;
  }
}

function errorLabel(code: string): string {
  return `${t('invoices.payment_error')} (${code})`;
}
</script>
