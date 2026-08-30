<template>
  <section class="space-y-4" aria-labelledby="payment-detail-heading">
    <p v-if="store.detailLoading || !payment" role="status" class="text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</p>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs text-[var(--ll-muted)]">{{ new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(payment.received_at)) }}</p>
          <h1 id="payment-detail-heading" class="text-xl font-bold">{{ payment.reference ?? payment.counterparty ?? payment.id }}</h1>
        </div>
        <span class="font-mono text-lg tabular-nums">{{ formatMinor(payment.amount_minor, payment.currency) }}</span>
      </header>

      <p v-if="outcome" class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3 text-sm" role="status">{{ outcome }}</p>
      <p v-if="store.actionError" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-700" role="alert">
        {{ errorLabel(store.actionError) }}
      </p>

      <Card :title="t('invoices.payment_allocations')">
        <p class="mb-3 text-sm text-[var(--ll-muted)]">
          {{ t('invoices.open_minor') }}: <span class="font-mono">{{ formatMinor(payment.unapplied_minor, payment.currency) }}</span>
        </p>
        <PaymentAllocationEditor v-model="lines" :suggestions="store.suggestions" />
        <Btn data-action="allocate" class="mt-3" :loading="store.actionLoading" :disabled="lines.length === 0" @click="allocate">
          {{ t('invoices.allocation_apply') }}
        </Btn>
      </Card>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute } from 'vue-router';
import { Btn, Card } from '@spa/ui';
import PaymentAllocationEditor from '@spa/modules/finance/components/PaymentAllocationEditor.vue';
import { formatMinor } from '@spa/modules/finance/models/money';
import type { AllocationLineInput } from '@spa/modules/finance/models/payment';
import { usePaymentsStore } from '@spa/modules/finance/stores/payments';

const route = useRoute();
const store = usePaymentsStore();
const locale = document.documentElement.lang || 'de-DE';
const id = computed(() => String(route.params.payment));
const payment = computed(() => store.current?.id === id.value ? store.current : null);
const outcome = ref<string | null>(null);
const lines = ref<AllocationLineInput[]>([]);

onMounted(async () => {
  await Promise.all([
    store.loadPayment(id.value),
    store.loadSuggestions(id.value).catch(() => undefined),
  ]).catch(() => undefined);
});

async function allocate(): Promise<void> {
  try {
    const result = await store.allocate(id.value, lines.value, payment.value?.version ?? null, globalThis.crypto.randomUUID());
    outcome.value = t('invoices.allocation_applied');
    lines.value = [];
    void result;
  } catch {
    // The typed store error is rendered as the workflow result.
  }
}

function errorLabel(code: string): string {
  return `${t('invoices.payment_error')} (${code})`;
}
</script>
