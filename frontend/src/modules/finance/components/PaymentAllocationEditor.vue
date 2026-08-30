<template>
  <div class="space-y-3">
    <div v-for="(line, index) in modelValue" :key="index" class="flex flex-wrap items-end gap-3">
      <TextField
        data-field="invoice-id"
        :model-value="line.invoice_id"
        :label="t('invoices.allocation_invoice_id')"
        class="min-w-64 flex-1"
        @update:model-value="patch(index, 'invoice_id', $event)"
      />
      <TextField
        data-field="amount"
        :model-value="line.amount"
        inputmode="decimal"
        :label="t('invoices.allocation_amount')"
        class="w-40"
        @update:model-value="patch(index, 'amount', $event)"
      />
      <Btn variant="ghost" size="sm" icon="delete" data-action="remove-line" :aria-label="t('invoices.quote_line_remove')" @click="remove(index)" />
    </div>
    <Btn variant="outline" size="sm" icon="add" data-action="add-line" @click="add">{{ t('invoices.allocation_add_line') }}</Btn>

    <div v-if="suggestions && suggestions.status !== 'none'" class="rounded-lg border border-[var(--ll-border)] p-3">
      <p class="mb-2 text-xs font-medium text-[var(--ll-muted)]">
        {{ t(suggestions.status === 'suggested' ? 'invoices.allocation_suggested' : 'invoices.allocation_ambiguous') }}
      </p>
      <ul class="space-y-2">
        <li
          v-for="candidate in suggestions.candidates"
          :key="candidate.invoice_id"
          class="flex flex-wrap items-center justify-between gap-2 text-sm"
        >
          <span>{{ candidate.number }} — {{ formatMinor(candidate.open_minor, candidate.currency) }} <span class="text-xs text-[var(--ll-muted)]">({{ t(`invoices.allocation_reason_${candidate.reason}`) }})</span></span>
          <Btn size="sm" variant="outline" @click="useSuggestion(candidate)">{{ t('invoices.allocation_use_suggestion') }}</Btn>
        </li>
      </ul>
      <p v-if="suggestions.requires_confirmation" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
        {{ t('invoices.allocation_requires_confirmation') }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { Btn, TextField } from '@spa/ui';
import { formatMinor } from '@spa/modules/finance/models/money';
import type { AllocationLineInput, PaymentSuggestionCandidate, PaymentSuggestions } from '@spa/modules/finance/models/payment';

const props = defineProps<{ modelValue: AllocationLineInput[]; suggestions?: PaymentSuggestions | null }>();
const emit = defineEmits<{ 'update:modelValue': [AllocationLineInput[]] }>();

function patch<K extends keyof AllocationLineInput>(index: number, key: K, value: AllocationLineInput[K]): void {
  emit('update:modelValue', props.modelValue.map((line, offset) => offset === index ? { ...line, [key]: value } : line));
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, { invoice_id: '', amount: '' }]);
}

function remove(index: number): void {
  emit('update:modelValue', props.modelValue.filter((_, offset) => offset !== index));
}

function useSuggestion(candidate: PaymentSuggestionCandidate): void {
  const open = candidate.open_minor.startsWith('-')
    ? `-${candidate.open_minor.slice(1).padStart(3, '0')}`
    : candidate.open_minor.padStart(3, '0');
  const negative = open.startsWith('-');
  const digits = negative ? open.slice(1) : open;
  const amount = `${negative ? '-' : ''}${digits.slice(0, -2) || '0'}.${digits.slice(-2)}`;
  emit('update:modelValue', [...props.modelValue, { invoice_id: candidate.invoice_id, amount }]);
}
</script>
