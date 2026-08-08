<template>
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('seclog.title') }}</v-toolbar-title>
      <v-spacer />
      <v-btn variant="tonal" :prepend-icon="mdiDownload" href="/api/v1/security-log/export?export=csv">CSV</v-btn>
    </v-toolbar>
    <v-divider />
    <v-data-table :headers="headers" :items="s.audit" :loading="loading" density="compact" :items-per-page="25">
      <template #[`item.meta`]="{ item }">
        <code class="text-caption">{{ item.meta ? JSON.stringify(item.meta) : '' }}</code>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiDownload } from '@mdi/js';
import { useSettingsStore } from '@spa/stores/settings';

const s = useSettingsStore();
const loading = ref(false);
const headers = [
  { title: t('common.date'), key: 'created_at' },
  { title: 'Action', key: 'action' },
  { title: 'Actor', key: 'actor' },
  { title: 'IP', key: 'ip' },
  { title: 'Meta', key: 'meta', sortable: false },
];
onMounted(async () => { loading.value = true; try { await s.loadAudit(); } finally { loading.value = false; } });
</script>
