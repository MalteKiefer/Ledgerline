<template>
  <div>
    <!-- Sidecar status + operator actions -->
    <Card class="mb-4">
      <template #header>
        <Icon name="smart_toy" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('gallery.gs_sidecar') }}</h2>
      </template>
      <div class="flex items-center gap-2 text-sm">
        <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="sidecarDot" />
        <span>{{ t('gallery.gs_sidecar_' + (status?.sidecar ?? 'down')) }}</span>
        <span v-if="st?.effective.url" class="ml-2 font-mono text-xs text-[var(--ll-muted)]">{{ st.effective.url }}</span>
      </div>
      <div class="mt-4 rounded-lg border border-[var(--ll-border)] bg-[var(--ll-bg)] p-3">
        <p class="mb-2 text-xs text-[var(--ll-muted)]">{{ t('gallery.gs_operator_hint') }}</p>
        <div v-for="(cmd, key) in operator" :key="key" class="mb-1.5 flex items-center gap-2">
          <span class="w-16 shrink-0 text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.gs_op_' + key) }}</span>
          <code class="min-w-0 flex-1 truncate rounded bg-black/[0.06] px-2 py-1 font-mono text-xs dark:bg-white/10">{{ cmd }}</code>
          <button class="rounded p-1 text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('common.copy')" @click="copy(cmd)"><Icon name="content_copy" :size="15" /></button>
        </div>
      </div>
    </Card>

    <!-- ML settings -->
    <Card class="mb-4">
      <template #header>
        <Icon name="tune" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('gallery.gs_ml') }}</h2>
      </template>
      <div class="space-y-3">
        <label class="flex items-center justify-between gap-3 text-sm">
          <span><span class="font-medium">{{ t('gallery.gs_semantic') }}</span><br><span class="text-xs text-[var(--ll-muted)]">{{ t('gallery.gs_semantic_hint') }}</span></span>
          <input v-model="form.ml_enabled" type="checkbox" class="h-5 w-5 accent-primary-500">
        </label>
        <label class="flex items-center justify-between gap-3 text-sm">
          <span><span class="font-medium">{{ t('gallery.gs_faces') }}</span><br><span class="text-xs text-[var(--ll-muted)]">{{ t('gallery.gs_faces_hint') }}</span></span>
          <input v-model="form.ml_face_enabled" type="checkbox" class="h-5 w-5 accent-primary-500">
        </label>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <TextField v-model="form.ml_url" :label="t('gallery.gs_url')" placeholder="http://ml:3003" />
          <TextField v-model="form.ml_clip_model" :label="t('gallery.gs_clip_model')" placeholder="ViT-B-32__openai" />
          <TextField v-model="form.ml_face_model" :label="t('gallery.gs_face_model')" placeholder="buffalo_l" />
          <TextField v-model="form.ml_search_distance" :label="t('gallery.gs_search_distance')" type="number" step="0.01" :hint="t('gallery.gs_distance_hint')" />
          <TextField v-model="form.ml_dup_distance" :label="t('gallery.gs_dup_distance')" type="number" step="0.01" :hint="t('gallery.gs_distance_hint')" />
          <TextField v-model="form.ml_face_min_score" :label="t('gallery.gs_face_min_score')" type="number" step="0.01" />
          <TextField v-model="form.ml_face_match_distance" :label="t('gallery.gs_face_match_distance')" type="number" step="0.01" :hint="t('gallery.gs_distance_hint')" />
        </div>
        <p class="text-xs text-[var(--ll-muted)]">{{ t('gallery.gs_inherit_hint') }}</p>
      </div>
    </Card>

    <!-- Worker queue -->
    <Card class="mb-4">
      <template #header>
        <Icon name="conveyor_belt" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('gallery.gs_queue') }}</h2>
      </template>
      <div class="flex flex-wrap items-center gap-4 text-sm">
        <span class="inline-flex items-center gap-1.5">
          <Icon :name="pending > 0 ? 'progress_activity' : 'check_circle'" :size="18" :class="pending > 0 ? 'animate-spin text-primary-500' : 'text-emerald-600'" />
          {{ pending > 0 ? t('gallery.gs_queue_pending', { n: String(pending) }) : t('gallery.gs_queue_idle') }}
        </span>
        <span v-if="failed > 0" class="inline-flex items-center gap-1.5 text-red-600">
          <Icon name="error" :size="18" /> {{ t('gallery.gs_queue_failed', { n: String(failed) }) }}
        </span>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        <Btn variant="ghost" size="sm" icon="playlist_remove" class="text-red-600" :disabled="pending === 0" :loading="busy === 'clear'" @click="queueAction('clear')">{{ t('gallery.gs_queue_clear') }}</Btn>
        <Btn variant="ghost" size="sm" icon="restart_alt" :disabled="failed === 0" :loading="busy === 'retry'" @click="queueAction('retry')">{{ t('gallery.gs_queue_retry') }}</Btn>
        <Btn variant="ghost" size="sm" icon="delete_sweep" class="text-red-600" :disabled="failed === 0" :loading="busy === 'flush'" @click="queueAction('flush')">{{ t('gallery.gs_queue_flush') }}</Btn>
      </div>
    </Card>

    <!-- Library counts -->
    <Card v-if="status" class="mb-4">
      <template #header>
        <Icon name="photo_library" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('gallery.gs_counts') }}</h2>
      </template>
      <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_photos') }}</span><span class="tabular-nums">{{ status.counts.photos }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_videos') }}</span><span class="tabular-nums">{{ status.counts.videos }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_dated') }}</span><span class="tabular-nums">{{ status.counts.with_date }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_located') }}</span><span class="tabular-nums">{{ status.counts.located }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_embedded') }}</span><span class="tabular-nums">{{ status.counts.embedded }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_faces') }}</span><span class="tabular-nums">{{ status.counts.faces }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_people') }}</span><span class="tabular-nums">{{ status.counts.people }}</span></div>
      </div>
    </Card>

    <!-- Rescan -->
    <Card class="mb-4">
      <template #header>
        <Icon name="restart_alt" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('gallery.gs_rescan') }}</h2>
      </template>
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('gallery.gs_rescan_hint') }}</p>
      <div class="flex flex-wrap gap-2">
        <Btn variant="soft" icon="face" :loading="busy === 'faces'" @click="rescan('faces')">{{ t('gallery.gs_rescan_faces') }}</Btn>
        <Btn variant="soft" icon="image_search" :loading="busy === 'embeddings'" @click="rescan('embeddings')">{{ t('gallery.gs_rescan_embeddings') }}</Btn>
        <Btn variant="soft" icon="schedule" :loading="busy === 'exif'" @click="rescan('exif')">{{ t('gallery.gs_rescan_exif') }}</Btn>
        <Btn variant="solid" icon="auto_awesome" :loading="busy === 'all'" @click="rescan('all')">{{ t('gallery.gs_rescan_all') }}</Btn>
      </div>
    </Card>

    <!-- Sticky save bar (ML settings) -->
    <div class="sticky bottom-3 z-10 flex justify-end rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 shadow-sm">
      <Btn variant="solid" :loading="saving" :disabled="loading" @click="save">{{ t('settings.save') }}</Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted, onUnmounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField } from '@spa/ui';

interface AdminGallery {
  settings: Record<string, unknown>;
  effective: { enabled: boolean; face_enabled: boolean; url: string; clip_model: string; face_model: string; search_max_distance: number; dup_max_distance: number; face_min_score: number; face_match_distance: number; vector: boolean };
  status: { sidecar: string; queue: { pending: number; failed: number }; counts: Record<string, number> };
  operator: Record<string, string>;
}

const { success, error } = useToast();
const st = ref<AdminGallery | null>(null);
const status = computed(() => st.value?.status);
const operator = computed(() => st.value?.operator ?? {});
const pending = computed(() => status.value?.queue.pending ?? 0);
const failed = computed(() => status.value?.queue.failed ?? 0);
const loading = ref(true);
const saving = ref(false);
const busy = ref('');
let poll: ReturnType<typeof setInterval> | null = null;

interface MlForm {
  ml_enabled: boolean; ml_face_enabled: boolean;
  ml_url: string; ml_clip_model: string; ml_face_model: string;
  ml_search_distance: string | number; ml_dup_distance: string | number;
  ml_face_min_score: string | number; ml_face_match_distance: string | number;
}
const form = reactive<MlForm>({
  ml_enabled: false, ml_face_enabled: false, ml_url: '', ml_clip_model: '', ml_face_model: '',
  ml_search_distance: '', ml_dup_distance: '', ml_face_min_score: '', ml_face_match_distance: '',
});

const sidecarDot = computed(() => {
  const s = status.value?.sidecar;
  if (s === 'up') return 'bg-emerald-500';
  if (s === 'disabled') return 'bg-neutral-400';
  return 'bg-red-500';
});

function apply(d: AdminGallery, fillForm = false) {
  st.value = d;
  if (fillForm) {
    const s = d.settings; const e = d.effective;
    form.ml_enabled = Boolean(s.ml_enabled ?? e.enabled);
    form.ml_face_enabled = Boolean(s.ml_face_enabled ?? e.face_enabled);
    form.ml_url = String(s.ml_url ?? e.url ?? '');
    form.ml_clip_model = String(s.ml_clip_model ?? e.clip_model ?? '');
    form.ml_face_model = String(s.ml_face_model ?? e.face_model ?? '');
    form.ml_search_distance = Number(s.ml_search_distance ?? e.search_max_distance ?? 0);
    form.ml_dup_distance = Number(s.ml_dup_distance ?? e.dup_max_distance ?? 0);
    form.ml_face_min_score = Number(s.ml_face_min_score ?? e.face_min_score ?? 0);
    form.ml_face_match_distance = Number(s.ml_face_match_distance ?? e.face_match_distance ?? 0);
  }
}

async function load(fillForm = false) { try { apply(await api.get<AdminGallery>('/api/v1/admin/gallery'), fillForm); } catch { /* keep */ } }
onMounted(async () => { await load(true); loading.value = false; poll = setInterval(() => load(false), 4000); });
onUnmounted(() => { if (poll) clearInterval(poll); });

function num(v: unknown): number | null { const n = Number(v); return v === '' || v === null || Number.isNaN(n) ? null : n; }
function str(v: unknown): string | null { const s = String(v ?? '').trim(); return s === '' ? null : s; }

async function save() {
  saving.value = true;
  try {
    await api.put('/api/v1/admin/gallery', {
      ml_enabled: !!form.ml_enabled, ml_face_enabled: !!form.ml_face_enabled,
      ml_url: str(form.ml_url), ml_clip_model: str(form.ml_clip_model), ml_face_model: str(form.ml_face_model),
      ml_search_distance: num(form.ml_search_distance), ml_dup_distance: num(form.ml_dup_distance),
      ml_face_min_score: num(form.ml_face_min_score), ml_face_match_distance: num(form.ml_face_match_distance),
    });
    success(t('common.saved'));
    await load(false);
  } catch { error(t('common.error')); } finally { saving.value = false; }
}

async function queueAction(a: 'clear' | 'retry' | 'flush') {
  busy.value = a;
  try { await api.post(`/api/v1/admin/gallery/queue/${a}`); success(t('common.saved')); await load(false); }
  catch { error(t('common.error')); } finally { busy.value = ''; }
}

async function rescan(scope: 'faces' | 'embeddings' | 'exif' | 'all') {
  busy.value = scope;
  try { const r = await api.post<{ queued: number }>('/api/v1/admin/gallery/reprocess', { scope }); success(t('gallery.reprocess_queued', { n: String(r.queued) })); await load(false); }
  catch { error(t('common.error')); } finally { busy.value = ''; }
}

async function copy(text: string) {
  try { await navigator.clipboard.writeText(text); success(t('common.copied')); } catch { /* clipboard blocked */ }
}
</script>
