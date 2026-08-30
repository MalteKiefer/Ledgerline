<template>
  <div class="space-y-1 text-sm" :aria-busy="stale">
    <p v-if="stale" class="text-amber-600 dark:text-amber-400" role="status">
      {{ t('invoices.quote_totals_stale') }}
    </p>
    <div class="flex justify-between gap-4">
      <span class="text-[var(--ll-muted)]">{{ t('invoices.net') }}</span>
      <span class="font-mono tabular-nums">{{ exactMinor(totals.net_minor, totals.currency) }}</span>
    </div>
    <div class="flex justify-between gap-4">
      <span class="text-[var(--ll-muted)]">{{ t('invoices.vat') }}</span>
      <span class="font-mono tabular-nums">{{ exactMinor(totals.vat_minor, totals.currency) }}</span>
    </div>
    <div class="flex justify-between gap-4 border-t border-[var(--ll-border)] pt-1 font-semibold">
      <span>{{ t('invoices.gross') }}</span>
      <span class="font-mono tabular-nums">{{ exactMinor(totals.gross_minor, totals.currency) }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import type { MoneyTotals } from '@spa/modules/finance/models/money';

withDefaults(defineProps<{ totals: MoneyTotals; stale?: boolean }>(), { stale: false });

function exactMinor(value: string, currency: string): string {
  if (!/^-?(?:0|[1-9][0-9]*)$/.test(value)) return `${value} ${currency}`;
  const negative = value.startsWith('-');
  const digits = (negative ? value.slice(1) : value).padStart(3, '0');
  const integer = digits.slice(0, -2);
  const fraction = digits.slice(-2);
  const locale = document.documentElement.lang || 'de-DE';

  try {
    const number = new Intl.NumberFormat(locale, { style: 'currency', currency, minimumFractionDigits: 2 });
    const group = number.formatToParts(1000).find(({ type }) => type === 'group')?.value ?? '.';
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, group);
    let usedInteger = false;
    return number.formatToParts(negative ? -0.01 : 0.01).map((part) => {
      if (part.type === 'integer') {
        if (usedInteger) return '';
        usedInteger = true;
        return grouped;
      }
      if (part.type === 'fraction') return fraction;
      if (part.type === 'group') return '';
      return part.value;
    }).join('');
  } catch {
    return `${negative ? '-' : ''}${integer}.${fraction} ${currency}`;
  }
}
</script>
