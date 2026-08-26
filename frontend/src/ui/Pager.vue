<template>
  <!-- Renders nothing when everything fits on one page: a pager that only ever
       says "1 / 1" is chrome, not information. -->
  <div v-if="total > options[0]" class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--ll-border)] px-4 py-2.5 text-sm">
    <span class="text-[var(--ll-muted)] tabular-nums">{{ rangeLabel }}</span>
    <div class="flex items-center gap-2">
      <Select
        :model-value="perPage"
        :options="options.map((n) => ({ title: String(n), value: n }))"
        class="w-20"
        @update:model-value="$emit('update:perPage', Number($event))"
      />
      <Btn v-if="pageCount > 2" variant="ghost" size="sm" icon="first_page" :disabled="page <= 1" :title="t('common.first_page')" @click="$emit('update:page', 1)" />
      <Btn variant="ghost" size="sm" icon="chevron_left" :disabled="page <= 1" :title="t('common.previous')" @click="$emit('update:page', page - 1)" />
      <!-- The page number is an input, not a label: at 335 pages, stepping one
           at a time is the only way to reach the middle otherwise. -->
      <span class="flex items-center gap-1 tabular-nums">
        <input
          :value="page" type="number" min="1" :max="pageCount" inputmode="numeric"
          class="w-14 rounded-md border border-[var(--ll-border)] bg-transparent px-1.5 py-0.5 text-center text-sm focus:border-primary-500 focus:outline-none"
          :aria-label="t('common.page')"
          @keyup.enter="jump(($event.target as HTMLInputElement).value)"
          @blur="jump(($event.target as HTMLInputElement).value)"
        >
        <span class="text-[var(--ll-muted)]">/ {{ pageCount }}</span>
      </span>
      <Btn variant="ghost" size="sm" icon="chevron_right" :disabled="page >= pageCount" :title="t('common.next')" @click="$emit('update:page', page + 1)" />
      <Btn v-if="pageCount > 2" variant="ghost" size="sm" icon="last_page" :disabled="page >= pageCount" :title="t('common.last_page')" @click="$emit('update:page', pageCount)" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Table pager. The parent owns `page`/`perPage` (v-model on both) and does the
 * slicing — this only renders the controls and the "x–y of n" read-out, so it
 * works the same over a client-side array and a server-paged list.
 */
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import Btn from './Btn.vue';
import Select from './Select.vue';

const props = withDefaults(defineProps<{
  page: number;
  perPage: number;
  total: number;
  options?: number[];
}>(), { options: () => [10, 25, 50, 100] });

const emit = defineEmits<{ 'update:page': [number]; 'update:perPage': [number] }>();

const pageCount = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));

/** Typed page numbers are clamped rather than rejected: 0 or 900 means an end. */
function jump(raw: string) {
  const n = Number(raw);
  if (!Number.isFinite(n)) return;
  const target = Math.min(pageCount.value, Math.max(1, Math.round(n)));
  if (target !== props.page) emit('update:page', target);
}
const rangeLabel = computed(() => {
  if (!props.total) return '—';
  const from = (props.page - 1) * props.perPage + 1;
  const to = Math.min(props.total, props.page * props.perPage);
  return `${from}–${to} / ${props.total}`;
});
</script>
