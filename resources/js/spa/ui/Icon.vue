<template>
  <span class="msym" :class="fill ? 'msym-fill' : ''" :style="{ fontSize: size + 'px' }" aria-hidden="true"><slot>{{ name }}</slot></span>
</template>

<script setup lang="ts">
// Material Symbols ligature icon. Pass the ligature name (e.g. "search").
defineProps<{ name?: string; size?: number | string; fill?: boolean }>();
</script>
