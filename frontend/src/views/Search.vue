<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { Card, Icon } from '@spa/ui';

interface Item { id: number; title: string; subtitle: string | null }
interface Group { module: string; items: Item[] }

const route = useRoute();
const router = useRouter();
const q = ref(String(route.query.q ?? ''));
const groups = ref<Group[]>([]);
const loading = ref(false);
let seq = 0;

// module → { route name, icon }
const MODULES: Record<string, { name: string; icon: string }> = {
  files: { name: 'files', icon: 'folder' },
  notes: { name: 'notes', icon: 'sticky_note_2' },
  gallery: { name: 'gallery', icon: 'photo_library' },
  contacts: { name: 'contacts', icon: 'contacts' },
  mail: { name: 'mail', icon: 'mail' },
  calendar: { name: 'calendar', icon: 'calendar_month' },
  finance: { name: 'finance', icon: 'account_balance' },
};

async function run() {
  const query = q.value.trim();
  if (query.length < 2) { groups.value = []; return; }
  const mine = ++seq;
  loading.value = true;
  try {
    const r = await api.get<{ groups: Group[] }>(`/api/v1/search?q=${encodeURIComponent(query)}`);
    if (mine === seq) groups.value = r.groups ?? [];
  } catch { if (mine === seq) groups.value = []; } finally { if (mine === seq) loading.value = false; }
}

function open(module: string) {
  const m = MODULES[module];
  if (m) router.push({ name: m.name, query: { q: q.value.trim() } });
}

watch(() => route.query.q, (v) => { q.value = String(v ?? ''); run(); }, { immediate: true });

const total = () => groups.value.reduce((n, g) => n + g.items.length, 0);
</script>

<template>
  <div class="mx-auto max-w-3xl p-4">
    <div class="relative mb-4">
      <Icon name="search" :size="20" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ll-muted)]" />
      <input
        v-model="q" :placeholder="t('common.search')" autofocus
        class="w-full rounded-xl border border-[var(--ll-border)] bg-transparent py-2.5 pl-10 pr-3 text-base focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        @keyup.enter="run"
      >
    </div>

    <div v-if="loading" class="p-8 text-center text-sm text-[var(--ll-muted)]">…</div>
    <div v-else-if="q.trim().length < 2" class="p-8 text-center text-sm text-[var(--ll-muted)]">{{ t('search.hint') }}</div>
    <div v-else-if="total() === 0" class="p-8 text-center text-sm text-[var(--ll-muted)]">{{ t('search.empty') }}</div>

    <div v-else class="flex flex-col gap-4">
      <Card v-for="g in groups" :key="g.module" class="!p-0">
        <button class="flex w-full items-center gap-2 border-b border-[var(--ll-border)] px-4 py-2 text-left" @click="open(g.module)">
          <Icon :name="MODULES[g.module]?.icon ?? 'search'" :size="18" class="text-[var(--ll-muted)]" />
          <span class="text-sm font-medium">{{ t('messages.nav.' + g.module) }}</span>
          <span class="text-xs text-[var(--ll-muted)]">{{ g.items.length }}</span>
          <Icon name="chevron_right" :size="16" class="ml-auto text-[var(--ll-muted)]" />
        </button>
        <button
          v-for="it in g.items" :key="g.module + it.id"
          class="flex w-full items-center gap-3 px-4 py-2 text-left hover:bg-black/[0.04] dark:hover:bg-white/5"
          @click="open(g.module)"
        >
          <div class="min-w-0 flex-1">
            <div class="truncate text-sm">{{ it.title }}</div>
            <div v-if="it.subtitle" class="truncate text-xs text-[var(--ll-muted)]">{{ it.subtitle }}</div>
          </div>
        </button>
      </Card>
    </div>
  </div>
</template>
