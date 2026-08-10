<template>
  <label class="block">
    <span v-if="label" class="block text-xs font-medium text-[var(--ll-muted)] mb-1.5">{{ label }}</span>
    <span class="relative flex items-center">
      <Icon v-if="icon" :name="icon" :size="18" class="absolute left-3 text-[var(--ll-muted)]" />
      <input
        :type="type ?? 'text'"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :inputmode="inputmode"
        :list="list"
        class="w-full rounded-lg border bg-transparent text-sm text-[var(--ll-fg)] placeholder:text-[var(--ll-muted)] transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 disabled:opacity-60"
        :class="[icon ? 'pl-10 pr-3' : 'px-3', 'py-2', error ? 'border-red-400' : 'border-[var(--ll-border)]']"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        @keyup.enter="$emit('enter')"
      >
    </span>
    <span v-if="error" class="block text-xs text-red-500 mt-1">{{ error }}</span>
    <span v-else-if="hint" class="block text-xs text-[var(--ll-muted)] mt-1">{{ hint }}</span>
  </label>
</template>

<script setup lang="ts">
import Icon from './Icon.vue';
defineProps<{
  modelValue?: string | number | null;
  label?: string; placeholder?: string; type?: string; icon?: string;
  hint?: string; error?: string; disabled?: boolean; autocomplete?: string; list?: string;
  inputmode?: 'text' | 'email' | 'search' | 'tel' | 'url' | 'none' | 'numeric' | 'decimal';
}>();
defineEmits<{ 'update:modelValue': [string]; enter: [] }>();
</script>
