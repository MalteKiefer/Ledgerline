<template>
  <Card :body-class="'p-0'">
    <template #header>
      <Icon name="notifications" :size="18" class="text-[var(--ll-muted)]" />
      <h2 class="text-sm font-semibold">{{ t('settings.notifications_section') }}</h2>
    </template>
    <template #actions><Btn variant="ghost" size="sm" @click="markAll">{{ t('notifications.mark_all_read') }}</Btn></template>
    <div class="divide-y divide-[var(--ll-border)]">
      <div v-for="n in items" :key="n.id" class="flex items-center gap-3 px-5 py-3" :class="!n.read ? 'bg-primary-500/5' : ''">
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium">{{ n.title }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ n.body }}</div>
        </div>
        <button v-if="!n.read" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-black/[0.04] dark:hover:bg-white/5" @click="markRead(n)"><Icon name="check" :size="17" class="text-[var(--ll-muted)]" /></button>
      </div>
      <div v-if="!items.length" class="px-5 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
    </div>
  </Card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Btn, Card } from '@spa/ui';
import { api } from '@spa/api/client';

interface Note { id: string | number; title: string; body: string; read: boolean }
const items = ref<Note[]>([]);
onMounted(load);
async function load() { const r = await api.get<{ items?: Note[] }>('/api/v1/notifications'); items.value = r.items ?? []; }
async function markRead(n: Note) { await api.post(`/api/v1/notifications/${n.id}/read`); n.read = true; }
async function markAll() { await api.post('/api/v1/notifications/read-all'); items.value.forEach((n) => (n.read = true)); }
</script>
