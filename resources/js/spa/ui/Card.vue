<template>
  <div class="rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] shadow-[0_1px_2px_rgba(20,16,40,.04),0_2px_10px_rgba(20,16,40,.04)]">
    <div v-if="$slots.header || title" class="flex items-center gap-2 px-5 py-3.5 border-b border-[var(--ll-border)]">
      <slot name="header"><h2 class="text-sm font-semibold">{{ title }}</h2></slot>
      <div class="ml-auto flex items-center gap-2"><slot name="actions" /></div>
    </div>
    <div :class="bodyClass ?? 'p-5'"><slot /></div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ title?: string; bodyClass?: string }>();
</script>
