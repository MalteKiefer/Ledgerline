<template>
  <v-card rounded="xl" border flat>
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('settings.notifications_section') }}</v-toolbar-title>
      <v-spacer />
      <v-btn variant="text" size="small" @click="markAll">{{ t('notifications.mark_all_read') }}</v-btn>
    </v-toolbar>
    <v-divider />
    <v-list>
      <v-list-item v-for="n in items" :key="n.id" :title="n.title" :subtitle="n.body" :class="{ 'bg-surface-variant': !n.read }">
        <template #append>
          <v-btn v-if="!n.read" variant="text" size="small" :icon="mdiCheck" @click="markRead(n)" />
        </template>
      </v-list-item>
      <v-list-item v-if="!items.length" :title="t('common.none')" class="text-medium-emphasis" />
    </v-list>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiCheck } from '@mdi/js';
import { api } from '@spa/api/client';

interface Note { id: string | number; title: string; body: string; read: boolean }
const items = ref<Note[]>([]);
onMounted(load);
async function load() { const r = await api.get<{ items?: Note[] }>('/api/v1/notifications'); items.value = r.items ?? []; }
async function markRead(n: Note) { await api.post(`/api/v1/notifications/${n.id}/read`); n.read = true; }
async function markAll() { await api.post('/api/v1/notifications/read-all'); items.value.forEach((n) => (n.read = true)); }
</script>
