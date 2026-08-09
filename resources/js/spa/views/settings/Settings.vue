<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('settings.heading') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <Card body-class="p-0" class="w-full flex-shrink-0 self-start md:w-64">
        <RouterLink
          v-for="s in sections" :key="s.to" :to="{ name: s.to }"
          class="flex w-full items-center gap-3 border-b border-[var(--ll-border)] px-4 py-3 last:border-0 hover:bg-black/[0.04] dark:hover:bg-white/5"
          :class="isActive(s.to) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
        >
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" :class="isActive(s.to) ? 'bg-primary-500/15' : 'bg-black/[0.05] dark:bg-white/10'">
            <Icon :name="s.icon" :size="20" />
          </span>
          <span class="flex-1 text-sm font-medium">{{ t(s.label) }}</span>
          <Icon name="chevron_right" :size="18" class="text-[var(--ll-muted)]" />
        </RouterLink>
      </Card>
      <div class="min-w-0 flex-1">
        <RouterView />
      </div>
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
  { to: 'settings.notifications-config', icon: 'notifications', label: 'settings.notifications_section' },
  { to: 'settings.security', icon: 'security', label: 'settings.security_section' },
  { to: 'settings.files-limits', icon: 'folder', label: 'settings.files_limits_heading' },
  { to: 'settings.backup', icon: 'backup', label: 'settings.backup_section' },
  { to: 'settings.paperless', icon: 'description', label: 'settings.paperless_section' },
  { to: 'settings.system', icon: 'dns', label: 'settings.system_section' },
  { to: 'settings.security-log', icon: 'lock_reset', label: 'settings.seclog_title' },
];

function isActive(name: string): boolean { return route.name === name; }
</script>
