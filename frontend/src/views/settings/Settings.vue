<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('settings.heading') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <SectionNav :groups="settingsNavGroups" :active="isSettingsSectionActive" />
      <div class="min-w-0 flex-1">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute, RouterView } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { SectionNav, type SectionNavItem } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { computed, onMounted } from 'vue';
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
    { to: 'settings.virustotal', icon: 'security', label: 'settings.virustotal_section' },
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
const settingsNavGroups = computed(() => groups.map((group) => ({
  id: group.title,
  title: t(group.title),
  items: group.items.map((item) => ({ id: item.to, icon: item.icon, label: t(item.label), to: { name: item.to } })),
})));

function isActive(name: string): boolean { return route.name === name; }
function isSettingsSectionActive(item: SectionNavItem): boolean { return isActive(item.id); }
</script>
