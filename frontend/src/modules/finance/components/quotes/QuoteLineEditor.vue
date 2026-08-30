<template>
  <div class="space-y-3">
    <fieldset v-for="(line, index) in modelValue" :key="index" class="grid gap-3 rounded-lg border border-[var(--ll-border)] p-3 md:grid-cols-12">
      <legend class="px-1 text-xs text-[var(--ll-muted)]">{{ t('invoices.positions') }} {{ index + 1 }}</legend>
      <TextField :model-value="line.description" :label="t('invoices.description')" class="md:col-span-5" @update:model-value="patch(index, 'description', $event)" />
      <TextField :model-value="line.quantity" :label="t('invoices.qty')" inputmode="decimal" class="md:col-span-2" @update:model-value="patch(index, 'quantity', $event)" />
      <TextField :model-value="line.unit" :label="t('invoices.quote_unit')" class="md:col-span-2" @update:model-value="patch(index, 'unit', $event)" />
      <TextField :model-value="line.unit_price" :label="t('invoices.unit_price')" inputmode="decimal" class="md:col-span-2" @update:model-value="patch(index, 'unit_price', $event)" />
      <Btn variant="ghost" size="sm" icon="delete" :aria-label="t('invoices.quote_line_remove')" class="self-end" @click="remove(index)" />
      <TextField :model-value="line.tax_rate" :label="t('invoices.quote_tax_rate')" inputmode="decimal" class="md:col-span-2" @update:model-value="patch(index, 'tax_rate', $event)" />
      <label class="md:col-span-3">
        <span class="mb-1.5 block text-xs font-medium text-[var(--ll-muted)]">{{ t('invoices.quote_line_kind') }}</span>
        <select :value="line.kind" class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm" @change="patch(index, 'kind', ($event.target as HTMLSelectElement).value as QuoteLineKind)">
          <option value="service">{{ t('invoices.quote_line_service') }}</option>
          <option value="hardware">{{ t('invoices.quote_line_hardware') }}</option>
        </select>
      </label>
    </fieldset>
    <Btn variant="outline" size="sm" icon="add" @click="add">{{ t('invoices.add_position') }}</Btn>
  </div>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { Btn, TextField } from '@spa/ui';
import type { QuoteLineInput, QuoteLineKind } from '@spa/modules/finance/models/quote';

const props = defineProps<{ modelValue: QuoteLineInput[] }>();
const emit = defineEmits<{ 'update:modelValue': [QuoteLineInput[]] }>();

function patch<K extends keyof QuoteLineInput>(index: number, key: K, value: QuoteLineInput[K]): void {
  emit('update:modelValue', props.modelValue.map((line, offset) => offset === index ? { ...line, [key]: value } : line));
}

function add(): void {
  emit('update:modelValue', [...props.modelValue, emptyLine()]);
}

function remove(index: number): void {
  if (props.modelValue.length === 1) return;
  emit('update:modelValue', props.modelValue.filter((_, offset) => offset !== index));
}

function emptyLine(): QuoteLineInput {
  return { description: '', quantity: '1.0000', unit: 'pc', unit_price: '0.00', tax_rate: '19.00', kind: 'service', product_id: null };
}
</script>
