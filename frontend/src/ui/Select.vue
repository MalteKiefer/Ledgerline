<template>
  <label class="block">
    <span v-if="label" class="block text-xs font-medium text-[var(--ll-muted)] mb-1.5">{{ label }}</span>
    <span class="relative flex items-center">
      <select
        :value="modelValue"
        :disabled="disabled"
        class="w-full appearance-none rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 pr-9 text-sm text-[var(--ll-fg)] focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 disabled:opacity-60"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      >
        <option v-for="o in options" :key="String(o.value)" :value="o.value">{{ o.title }}</option>
      </select>
      <Icon name="expand_more" :size="18" class="pointer-events-none absolute right-2.5 text-[var(--ll-muted)]" />
    </span>
  </label>
</template>

<script setup lang="ts">
import Icon from './Icon.vue';
defineProps<{ modelValue?: string | number | null; label?: string; options: { title: string; value: string | number }[]; disabled?: boolean }>();
defineEmits<{ 'update:modelValue': [string] }>();
</script>
