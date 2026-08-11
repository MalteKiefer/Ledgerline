<template>
  <div>
    <!-- ML status -->
    <Card :title="t('gallery.settings_ml')" class="mb-4">
      <div v-if="s" class="space-y-2 text-sm">
        <div class="flex items-center justify-between">
          <span>{{ t('gallery.settings_semantic') }}</span>
          <span :class="s.ml.enabled ? 'text-emerald-600' : 'text-[var(--ll-muted)]'">{{ s.ml.enabled ? t('gallery.settings_ml_on') : t('gallery.settings_ml_off') }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span>{{ t('gallery.settings_faces') }}</span>
          <span :class="s.ml.face_enabled ? 'text-emerald-600' : 'text-[var(--ll-muted)]'">{{ s.ml.face_enabled ? t('gallery.settings_ml_on') : t('gallery.settings_ml_off') }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span>{{ t('gallery.settings_vector') }}</span>
          <span :class="s.ml.vector ? 'text-emerald-600' : 'text-amber-600'">{{ s.ml.vector ? t('gallery.settings_ml_on') : t('gallery.settings_ml_off') }}</span>
        </div>
        <div v-if="s.ml.clip_model" class="flex items-center justify-between text-[var(--ll-muted)]">
          <span>{{ t('gallery.settings_model') }} (CLIP)</span><span class="font-mono text-xs">{{ s.ml.clip_model }}</span>
        </div>
        <div v-if="s.ml.face_model" class="flex items-center justify-between text-[var(--ll-muted)]">
          <span>{{ t('gallery.settings_model') }} (Faces)</span><span class="font-mono text-xs">{{ s.ml.face_model }}</span>
        </div>
      </div>
    </Card>

    <!-- Worker queue -->
    <Card :title="t('gallery.settings_queue')" class="mb-4">
      <div class="flex items-center gap-3">
        <Icon :name="pending > 0 ? 'progress_activity' : 'check_circle'" :size="22" :class="pending > 0 ? 'animate-spin text-primary-500' : 'text-emerald-600'" />
        <span class="text-sm">{{ pending > 0 ? t('gallery.settings_queue_pending', { n: String(pending) }) : t('gallery.settings_queue_idle') }}</span>
      </div>
    </Card>

    <!-- Library counts -->
    <Card v-if="s" :title="t('gallery.settings_counts')" class="mb-4">
      <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-3">
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_photos') }}</span><span class="tabular-nums">{{ s.counts.photos }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_videos') }}</span><span class="tabular-nums">{{ s.counts.videos }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_dated') }}</span><span class="tabular-nums">{{ s.counts.with_date }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_located') }}</span><span class="tabular-nums">{{ s.counts.located }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_embedded') }}</span><span class="tabular-nums">{{ s.counts.embedded }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_faces') }}</span><span class="tabular-nums">{{ s.counts.faces }}</span></div>
        <div class="flex justify-between"><span class="text-[var(--ll-muted)]">{{ t('gallery.count_people') }}</span><span class="tabular-nums">{{ s.counts.people }}</span></div>
      </div>
    </Card>

    <!-- Rescan -->
    <Card :title="t('gallery.settings_rescan')">
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('gallery.settings_rescan_hint') }}</p>
      <div class="flex flex-wrap gap-2">
        <Btn variant="soft" icon="face" :loading="busy === 'faces'" @click="rescan('faces')">{{ t('gallery.reprocess_faces') }}</Btn>
        <Btn variant="soft" icon="image_search" :loading="busy === 'embeddings'" @click="rescan('embeddings')">{{ t('gallery.reprocess_embeddings') }}</Btn>
        <Btn variant="soft" icon="schedule" :loading="busy === 'exif'" @click="rescan('exif')">{{ t('gallery.rescan_exif') }}</Btn>
        <Btn variant="ghost" :loading="busy === 'all'" @click="rescan('all')">{{ t('gallery.reprocess_all') }}</Btn>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Icon } from '@spa/ui';
import { useGalleryStore, type MlStatus } from '@spa/stores/gallery';
import { useToast } from '@spa/composables/useToast';

const g = useGalleryStore();
const { success, error } = useToast();
const s = ref<MlStatus | null>(null);
const busy = ref<'' | 'faces' | 'embeddings' | 'exif' | 'all'>('');
const pending = computed(() => s.value?.queue.pending ?? 0);
let poll: ReturnType<typeof setInterval> | null = null;

async function load() { try { s.value = await g.mlStatus(); } catch { /* keep last */ } }
onMounted(() => { void load(); poll = setInterval(load, 4000); });
onUnmounted(() => { if (poll) clearInterval(poll); });

async function rescan(scope: 'faces' | 'embeddings' | 'exif' | 'all') {
  busy.value = scope;
  try { const r = await g.reprocess(scope); success(t('gallery.reprocess_queued', { n: String(r.queued) })); await load(); }
  catch { error(t('common.error')); }
  finally { busy.value = ''; }
}
</script>
