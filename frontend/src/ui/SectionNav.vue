<template>
  <nav class="w-full flex-shrink-0 self-start space-y-4 md:w-60" aria-label="Section navigation">
    <section v-for="group in groups" :key="group.id" class="space-y-0.5">
      <div v-if="group.title" class="px-2 pb-1 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ group.title }}</div>
      <template v-for="item in group.items" :key="item.id">
        <RouterLink
          v-if="item.to" :to="item.to"
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="active(item) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
        >
          <Icon :name="item.icon" :size="20" :class="active(item) ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
          <span class="flex-1 text-left">{{ item.label }}</span>
        </RouterLink>
        <button
          v-else type="button"
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="active(item) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          @click="$emit('select', item)"
        >
          <Icon :name="item.icon" :size="20" :class="active(item) ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
          <span class="flex-1 text-left">{{ item.label }}</span>
        </button>
      </template>
    </section>
  </nav>
</template>

<script setup lang="ts">
import { RouterLink, type RouteLocationRaw } from 'vue-router';
import Icon from './Icon.vue';

export interface SectionNavItem {
  id: string;
  label: string;
  icon: string;
  to?: RouteLocationRaw;
}

export interface SectionNavGroup {
  id: string;
  title?: string;
  items: SectionNavItem[];
}

defineProps<{
  groups: SectionNavGroup[];
  active: (item: SectionNavItem) => boolean;
}>();

defineEmits<{ select: [item: SectionNavItem] }>();
</script>
