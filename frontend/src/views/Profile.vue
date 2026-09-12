<template>
  <div>
    <h1 class="mb-4 text-xl font-bold">{{ t('pages.profile.title') }}</h1>
    <div class="flex flex-col gap-4 md:flex-row">
      <SectionNav :groups="profileNavGroups" :active="isProfileSectionActive" />
      <div class="min-w-0 flex-1">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { useRoute, RouterView } from 'vue-router';
import { SectionNav, type SectionNavItem } from '@spa/ui';

const route = useRoute();

const sections = computed(() => [
  { to: 'profile.account', icon: 'account_circle', label: 'account.nav_account' },
  { to: 'profile.appearance', icon: 'palette', label: 'account.nav_appearance' },
  { to: 'profile.security', icon: 'security', label: 'account.nav_security' },
  { to: 'profile.devices', icon: 'smartphone', label: 'account.nav_devices' },
  { to: 'profile.data', icon: 'database', label: 'account.hub_data_heading' },
]);
const profileNavGroups = computed(() => [{
  id: 'profile',
  items: sections.value.map((section) => ({ id: section.to, icon: section.icon, label: t(section.label), to: { name: section.to } })),
}]);

function isActive(name: string): boolean { return route.name === name; }
function isProfileSectionActive(item: SectionNavItem): boolean { return isActive(item.id); }
</script>
