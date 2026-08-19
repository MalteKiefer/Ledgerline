<template>
  <nav class="w-full flex-shrink-0 self-start rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] p-2 shadow-sm md:sticky md:top-4 md:w-64" aria-label="Section navigation">
    <section v-for="(group, groupIndex) in groups" :key="group.id" class="space-y-0.5" :class="groupIndex ? 'mt-2 border-t border-[var(--ll-border)] pt-2' : ''">
      <div v-if="group.title" class="px-2.5 pb-1 pt-0.5 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]">{{ group.title }}</div>
      <template v-for="item in group.items" :key="item.id">
        <RouterLink
          v-if="item.to" :to="item.to"
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors hover:bg-black/[0.04] focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 dark:hover:bg-white/5"
          :class="active(item) ? 'bg-primary-500/10 text-primary-700 shadow-sm dark:text-primary-300' : 'text-[var(--ll-text)]'"
        >
          <Icon :name="item.icon" :size="20" :class="active(item) ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
          <span class="flex-1 text-left">{{ item.label }}</span>
        </RouterLink>
        <button
          v-else type="button"
          class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors hover:bg-black/[0.04] focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 dark:hover:bg-white/5"
          :class="active(item) ? 'bg-primary-500/10 text-primary-700 shadow-sm dark:text-primary-300' : 'text-[var(--ll-text)]'"
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
