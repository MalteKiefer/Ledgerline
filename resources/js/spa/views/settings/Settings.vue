<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('settings.heading') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <Card body-class="p-0" class="w-full flex-shrink-0 self-start overflow-hidden md:w-64">
        <template v-for="group in groups" :key="group.title">
          <div class="border-b border-[var(--ll-border)] bg-black/[0.02] px-4 pb-1 pt-3 text-[0.7rem] font-semibold uppercase tracking-wide text-[var(--ll-muted)] dark:bg-white/[0.03]">{{ t(group.title) }}</div>
          <RouterLink
            v-for="s in group.items" :key="s.to" :to="{ name: s.to }"
            class="flex w-full items-center gap-3 border-b border-[var(--ll-border)] px-4 py-2.5 hover:bg-black/[0.04] dark:hover:bg-white/5"
            :class="isActive(s.to) ? 'bg-primary-500/10 text-primary-600 dark:text-primary-300' : ''"
          >
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg" :class="isActive(s.to) ? 'bg-primary-500/15' : 'bg-black/[0.05] dark:bg-white/10'">
              <Icon :name="s.icon" :size="18" />
            </span>
            <span class="flex-1 text-sm font-medium">{{ t(s.label) }}</span>
          </RouterLink>
        </template>
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
import { useAuthStore } from '@spa/stores/auth';
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

// Professional admin area — client gate (the API is the real gate: every
// settings fetch is can:manage-global-settings and 403s for non-admins).
onMounted(() => { if (!auth.isAdmin()) router.replace({ name: 'home' }); });

// Logically grouped admin navigation.
const groups = [
  { title: 'settings.grp_overview', items: [
    { to: 'settings.dashboard', icon: 'space_dashboard', label: 'dash.section' },
  ] },
  { title: 'settings.grp_access', items: [
    { to: 'settings.users', icon: 'group', label: 'settings.users_section' },
    { to: 'settings.groups', icon: 'badge', label: 'settings.groups_section' },
    { to: 'settings.sessions', icon: 'devices', label: 'settings.sessions_section' },
  ] },
  { title: 'settings.grp_security', items: [
    { to: 'settings.security', icon: 'security', label: 'settings.security_section' },
    { to: 'settings.security-log', icon: 'lock_reset', label: 'settings.seclog_title' },
    { to: 'settings.request-log', icon: 'monitoring', label: 'settings.request_log_section' },
    { to: 'settings.blocks', icon: 'block', label: 'settings.blocks_section' },
  ] },
  { title: 'settings.grp_modules', items: [
    { to: 'settings.company', icon: 'business', label: 'settings.company_section' },
    { to: 'settings.gallery', icon: 'photo_library', label: 'gallery.gs_section' },
    { to: 'settings.files-limits', icon: 'folder', label: 'settings.files_limits_heading' },
    { to: 'settings.paperless', icon: 'description', label: 'settings.paperless_section' },
    { to: 'settings.notifications-config', icon: 'notifications', label: 'settings.notifications_section' },
  ] },
  { title: 'settings.grp_system', items: [
    { to: 'settings.limits', icon: 'tune', label: 'settings.limits_section' },
    { to: 'settings.backup', icon: 'backup', label: 'settings.backup_section' },
    { to: 'settings.containers', icon: 'deployed_code', label: 'settings.containers_heading' },
    { to: 'settings.system', icon: 'dns', label: 'settings.system_section' },
  ] },
];

function isActive(name: string): boolean { return route.name === name; }
</script>
