<template>
  <div class="flex flex-col gap-4 md:flex-row">
    <Card body-class="p-0" class="w-full flex-shrink-0 self-start md:w-60">
      <nav class="space-y-0.5 p-2">
        <RouterLink
          v-for="s in sections"
          :key="s.to"
          :to="{ name: s.to }"
          class="flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="isActive(s.to) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : 'text-[var(--ll-fg)]'"
        >
          <Icon :name="s.icon" :size="20" :class="isActive(s.to) ? 'text-primary-600 dark:text-primary-300' : 'text-[var(--ll-muted)]'" />
          <span class="flex-1">{{ t(s.label) }}</span>
          <Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" />
        </RouterLink>
      </nav>
    </Card>
    <div class="min-w-0 flex-1">
      <RouterView />
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute, RouterLink, RouterView } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card } from '@spa/ui';

const route = useRoute();

const sections = [
  { to: 'settings.users', icon: 'group', label: 'settings.users_section' },
  { to: 'settings.groups', icon: 'badge', label: 'settings.groups_section' },
  { to: 'settings.company', icon: 'business', label: 'settings.company_section' },
  { to: 'settings.backup', icon: 'upload', label: 'settings.backup_section' },
  { to: 'settings.security-log', icon: 'lock_reset', label: 'settings.seclog_title' },
  { to: 'settings.notifications', icon: 'key', label: 'settings.notifications_section' },
];

function isActive(name: string): boolean { return route.name === name; }
</script>
