<template>
  <div class="d-flex flex-column flex-md-row ga-4">
    <v-card rounded="xl" border flat class="flex-shrink-0" width="240" style="align-self:flex-start">
      <v-list density="compact" nav>
        <v-list-item
          v-for="s in sections"
          :key="s.to"
          :to="{ name: s.to }"
          :prepend-icon="s.icon"
          :title="t(s.label)"
        />
      </v-list>
    </v-card>
    <div class="flex-grow-1 min-width-0">
      <router-view />
    </div>
  </div>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { mdiAccountGroup, mdiAccountMultiple, mdiDomain, mdiCloudUpload, mdiShieldAlert, mdiBellOutline } from '@mdi/js';

const sections = [
  { to: 'settings.users', icon: mdiAccountMultiple, label: 'settings.users_section' },
  { to: 'settings.groups', icon: mdiAccountGroup, label: 'settings.groups_section' },
  { to: 'settings.company', icon: mdiDomain, label: 'settings.company_section' },
  { to: 'settings.backup', icon: mdiCloudUpload, label: 'settings.backup_section' },
  { to: 'settings.security-log', icon: mdiShieldAlert, label: 'settings.seclog_title' },
  { to: 'settings.notifications', icon: mdiBellOutline, label: 'settings.notifications_section' },
];
</script>

<style scoped>
.min-width-0 { min-width: 0; }
</style>
