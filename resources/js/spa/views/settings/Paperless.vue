<template>
  <div>
    <!-- Connection config -->
    <Card class="mb-4">
      <template #header>
        <Icon name="description" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.paperless_heading') }}</h2>
      </template>
      <template #actions>
        <label class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
          <input v-model="cfg.paperless_enabled" type="checkbox" class="peer sr-only">
          <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
        </label>
      </template>
      <div class="rounded-lg bg-blue-500/10 px-3 py-2 text-sm text-blue-600 dark:text-blue-400 mb-4">{{ t('settings.paperless_desc') }}</div>
      <div class="space-y-4">
        <TextField v-model="cfg.paperless_url" :label="t('settings.paperless_url')" type="url" placeholder="https://…" />
        <TextField
          v-model="cfg.paperless_token" :label="t('settings.paperless_token')" type="password" autocomplete="new-password"
          :hint="t('settings.paperless_token_hint')"
        />
      </div>
      <div class="mt-3 flex items-center gap-2">
        <Btn variant="solid" :loading="savingCfg" :disabled="loadingCfg" @click="saveConfig">
          {{ t('settings.save') }}
        </Btn>
        <Btn variant="soft" icon="refresh" :loading="testing" :disabled="savingCfg" @click="testConnection">
          {{ t('settings.paperless_test') }}
        </Btn>
      </div>
    </Card>

    <!-- Cached quick-pick terms (existing) -->
    <Card :body-class="'p-0'">
      <template #header>
        <Icon name="description" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.paperless_cache_heading') }}</h2>
      </template>
      <template #actions><Btn variant="soft" size="sm" icon="refresh" :loading="syncing" @click="sync">{{ t('settings.paperless_sync_now') }}</Btn></template>
      <div class="p-5">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
          <TermList :title="t('settings.paperless_tags')" :items="terms.tags" />
          <TermList :title="t('settings.paperless_document_types')" :items="terms.document_types" />
          <TermList :title="t('settings.paperless_correspondents')" :items="terms.correspondents" />
        </div>
        <p class="mt-3 text-xs text-[var(--ll-muted)]">{{ t('settings.paperless_never_synced') }}: {{ syncedAt || '—' }}</p>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, defineComponent, h } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api, ApiError } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField, Badge } from '@spa/ui';

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
      h('div', { class: 'mb-1.5 text-[0.66rem] font-semibold uppercase tracking-wider text-[var(--ll-muted)]' }, p.title),
      p.items.length
        ? h('div', { class: 'flex flex-wrap gap-1' }, p.items.slice(0, 40).map((x) => h(Badge, { tone: 'primary' }, () => x)))
        : h('div', { class: 'text-sm text-[var(--ll-muted)]' }, '—'),
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
