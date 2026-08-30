<template>
  <div class="space-y-1 text-sm">
    <div class="flex justify-between gap-4">
      <span class="text-[var(--ll-muted)]">{{ t('invoices.net') }}</span>
      <span class="font-mono tabular-nums">{{ formatMinor(totals.net_minor, totals.currency) }}</span>
    </div>
    <div class="flex justify-between gap-4">
      <span class="text-[var(--ll-muted)]">{{ t('invoices.vat') }}</span>
      <span class="font-mono tabular-nums">{{ formatMinor(totals.vat_minor, totals.currency) }}</span>
    </div>
    <div class="flex justify-between gap-4 border-t border-[var(--ll-border)] pt-1 font-semibold">
      <span>{{ t('invoices.gross') }}</span>
      <span class="font-mono tabular-nums">{{ formatMinor(totals.gross_minor, totals.currency) }}</span>
    </div>
    <template v-if="openMinor !== undefined">
      <div class="flex justify-between gap-4">
        <span class="text-[var(--ll-muted)]">{{ t('invoices.allocated_minor') }}</span>
        <span class="font-mono tabular-nums">{{ formatMinor(allocatedMinor ?? '0', totals.currency) }}</span>
      </div>
      <div class="flex justify-between gap-4 font-semibold" :class="openTone">
        <span>{{ t('invoices.open_minor') }}</span>
        <span class="font-mono tabular-nums">{{ formatMinor(openMinor, totals.currency) }}</span>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { formatMinor, type DecimalIntegerString, type MoneyTotals } from '@spa/modules/finance/models/money';

const props = defineProps<{
  totals: MoneyTotals;
  allocatedMinor?: DecimalIntegerString;
  openMinor?: DecimalIntegerString;
}>();

const openTone = computed(() => props.openMinor === '0' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400');
</script>
