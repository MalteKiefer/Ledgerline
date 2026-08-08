<template>
  <v-card rounded="lg" border flat>
    <v-toolbar flat color="surface">
      <v-toolbar-title>{{ t('settings.paperless_section') }}</v-toolbar-title>
      <v-spacer />
      <v-btn variant="tonal" color="primary" :prepend-icon="mdiSync" :loading="syncing" @click="sync">{{ t('settings.paperless_sync_now') }}</v-btn>
    </v-toolbar>
    <v-divider />
    <v-card-text>
      <v-alert type="info" variant="tonal" density="compact" class="mb-4" :text="t('settings.paperless_desc')" />
      <v-row>
        <v-col cols="12" md="4"><TermList :title="t('settings.paperless_tags')" :items="terms.tags" /></v-col>
        <v-col cols="12" md="4"><TermList :title="t('settings.paperless_document_types')" :items="terms.document_types" /></v-col>
        <v-col cols="12" md="4"><TermList :title="t('settings.paperless_correspondents')" :items="terms.correspondents" /></v-col>
      </v-row>
      <p class="text-caption text-medium-emphasis mt-3">{{ t('settings.paperless_never_synced') }}: {{ syncedAt || '—' }}</p>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted, defineComponent, h } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiSync } from '@mdi/js';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';

const { success, error } = useToast();
const syncing = ref(false);
const syncedAt = ref('');
const terms = ref<{ tags: string[]; document_types: string[]; correspondents: string[] }>({ tags: [], document_types: [], correspondents: [] });

interface TermPayload { tags?: string[]; document_types?: string[]; correspondents?: string[]; synced_at?: string }

const TermList = defineComponent({
  props: { title: { type: String, required: true }, items: { type: Array as () => string[], default: () => [] } },
  setup(p) {
    return () => h('div', [
      h('div', { class: 'text-overline text-medium-emphasis mb-1' }, p.title),
      p.items.length
        ? h('div', { class: 'd-flex flex-wrap ga-1' }, p.items.slice(0, 40).map((x) => h('span', { class: 'v-chip v-chip--size-small text-caption px-2 py-1', style: 'background:rgba(167,139,250,.12);border-radius:6px' }, x)))
        : h('div', { class: 'text-caption text-disabled' }, '—'),
    ]);
  },
});

async function load() {
  try {
    const r = await api.get<TermPayload>('/api/v1/paperless/terms');
    terms.value = { tags: r.tags ?? [], document_types: r.document_types ?? [], correspondents: r.correspondents ?? [] };
    syncedAt.value = r.synced_at ?? '';
  } catch { /* not configured */ }
}
async function sync() {
  syncing.value = true;
  try { await api.post('/api/v1/paperless/sync'); await load(); success(t('common.saved')); }
  catch { error(t('settings.paperless_test_failed')); }
  finally { syncing.value = false; }
}
onMounted(load);
</script>
