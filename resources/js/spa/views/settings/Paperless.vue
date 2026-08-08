<template>
  <div class="mx-auto" style="max-width: 960px">
    <!-- Connection config -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiFileDocumentMultipleOutline" size="small" />
        {{ t('settings.paperless_heading') }}
        <v-spacer />
        <v-switch v-model="cfg.paperless_enabled" color="primary" density="compact" hide-details inset />
      </v-card-title>
      <v-divider />
      <v-card-text>
        <v-alert type="info" variant="tonal" density="compact" class="mb-4" :text="t('settings.paperless_desc')" />
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="cfg.paperless_url" :label="t('settings.paperless_url')" type="url" placeholder="https://…" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-text-field
              v-model="cfg.paperless_token"
              :label="t('settings.paperless_token')"
              type="password"
              autocomplete="new-password"
              :hint="t('settings.paperless_token_hint')"
              persistent-hint
              variant="outlined"
              density="comfortable"
            />
          </v-col>
        </v-row>
        <div class="d-flex align-center ga-2 mt-3">
          <v-btn color="primary" variant="flat" :prepend-icon="mdiContentSave" :loading="savingCfg" :disabled="loadingCfg" @click="saveConfig">
            {{ t('settings.save') }}
          </v-btn>
          <v-btn variant="tonal" :prepend-icon="mdiConnection" :loading="testing" :disabled="savingCfg" @click="testConnection">
            {{ t('settings.paperless_test') }}
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <!-- Cached quick-pick terms (existing) -->
    <v-card rounded="xl" border flat>
      <v-toolbar flat color="surface">
        <v-toolbar-title>{{ t('settings.paperless_cache_heading') }}</v-toolbar-title>
        <v-spacer />
        <v-btn variant="tonal" color="primary" :prepend-icon="mdiSync" :loading="syncing" @click="sync">{{ t('settings.paperless_sync_now') }}</v-btn>
      </v-toolbar>
      <v-divider />
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4"><TermList :title="t('settings.paperless_tags')" :items="terms.tags" /></v-col>
          <v-col cols="12" md="4"><TermList :title="t('settings.paperless_document_types')" :items="terms.document_types" /></v-col>
          <v-col cols="12" md="4"><TermList :title="t('settings.paperless_correspondents')" :items="terms.correspondents" /></v-col>
        </v-row>
        <p class="text-caption text-medium-emphasis mt-3">{{ t('settings.paperless_never_synced') }}: {{ syncedAt || '—' }}</p>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, defineComponent, h } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiSync, mdiFileDocumentMultipleOutline, mdiContentSave, mdiConnection } from '@mdi/js';
import { api, ApiError } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';

const { success, error } = useToast();
const syncing = ref(false);
const syncedAt = ref('');
const terms = ref<{ tags: string[]; document_types: string[]; correspondents: string[] }>({ tags: [], document_types: [], correspondents: [] });

interface TermPayload { tags?: string[]; document_types?: string[]; correspondents?: string[]; synced_at?: string }

// --- Connection config ---
interface PaperlessConfig {
  paperless_enabled: boolean;
  paperless_url: string | null;
  has_token: boolean;
  counts: { tag: number; document_type: number; correspondent: number };
}

const cfg = reactive<{ paperless_enabled: boolean; paperless_url: string; paperless_token: string; has_token: boolean }>({
  paperless_enabled: false,
  paperless_url: '',
  paperless_token: '',
  has_token: false,
});

const loadingCfg = ref(true);
const savingCfg = ref(false);
const testing = ref(false);

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

function applyConfig(c: PaperlessConfig) {
  cfg.paperless_enabled = !!c.paperless_enabled;
  cfg.paperless_url = c.paperless_url ?? '';
  cfg.has_token = !!c.has_token;
  cfg.paperless_token = '';
}

async function loadConfig() {
  try {
    applyConfig(await api.get<PaperlessConfig>('/api/v1/paperless/config'));
  } catch { /* not configured */ }
  finally { loadingCfg.value = false; }
}

async function saveConfig() {
  savingCfg.value = true;
  try {
    const body: Record<string, unknown> = {
      paperless_enabled: cfg.paperless_enabled,
      paperless_url: cfg.paperless_url,
    };
    if (cfg.paperless_token) body.paperless_token = cfg.paperless_token;
    applyConfig(await api.put<PaperlessConfig>('/api/v1/paperless/config', body));
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  } finally {
    savingCfg.value = false;
  }
}

async function testConnection() {
  testing.value = true;
  try {
    const res = await api.post<{ ok: boolean; detail?: string }>('/api/v1/paperless/config/test');
    if (res.ok) success(t('settings.paperless_test_ok'));
    else error(res.detail || t('settings.paperless_test_failed'));
  } catch (e) {
    const detail = e instanceof ApiError && e.body && typeof e.body === 'object' ? (e.body as { detail?: string }).detail : undefined;
    error(detail || t('settings.paperless_test_failed'));
  } finally {
    testing.value = false;
  }
}

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
onMounted(() => { loadConfig(); load(); });
</script>
