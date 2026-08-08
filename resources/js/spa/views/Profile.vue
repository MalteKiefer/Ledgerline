<template>
  <div>
    <h1 class="text-h5 mb-4">{{ t('pages.profile.title') }}</h1>
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
  </div>
</template>

<script setup lang="ts">
import { trans as t } from 'laravel-vue-i18n';
import { mdiAccountCircle, mdiPalette, mdiShieldCheck, mdiCellphone, mdiDatabase } from '@mdi/js';

const sections = [
  { to: 'profile.account', icon: mdiAccountCircle, label: 'account.nav_account' },
  { to: 'profile.appearance', icon: mdiPalette, label: 'account.nav_appearance' },
  { to: 'profile.security', icon: mdiShieldCheck, label: 'account.nav_security' },
  { to: 'profile.devices', icon: mdiCellphone, label: 'account.nav_devices' },
  { to: 'profile.data', icon: mdiDatabase, label: 'account.hub_data_heading' },
];
</script>

<style scoped>
.min-width-0 { min-width: 0; }
</style>
