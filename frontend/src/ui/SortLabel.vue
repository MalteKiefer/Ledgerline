<template>
  <span class="inline-flex items-center gap-0.5" :class="justify === 'end' ? 'justify-end' : ''">
    {{ label }}
    <Icon v-if="sort.key === activeKey" :name="sort.dir === 'asc' ? 'arrow_upward' : 'arrow_downward'" :size="14" />
  </span>
</template>

<script setup lang="ts">
// A sortable <th>'s label + direction arrow — the header itself owns the click
// handler (@click="sortBy(key)"); this only renders the label and, when this
// column is the active sort key, the current direction.
import Icon from './Icon.vue';

defineProps<{
  label: string;
  activeKey: string;
  sort: { key: string; dir: 'asc' | 'desc' };
  justify?: 'start' | 'end';
}>();
</script>
