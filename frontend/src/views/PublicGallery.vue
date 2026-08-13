<template>
  <div class="min-h-screen bg-[var(--ll-bg)] p-4 text-[var(--ll-fg)]">
    <div class="mx-auto w-full max-w-6xl">
      <div class="mb-6 flex items-center justify-center gap-2.5 pt-6">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 text-white"><Icon name="bolt" :size="22" /></span>
        <span class="text-xl font-bold">Ledgerline</span>
      </div>

      <div v-if="loading" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('common.loading') }}</div>

      <Card v-else-if="state === 'invalid' || state === 'expired'" :body-class="'p-8 text-center'">
        <Icon name="link_off" :size="40" class="mx-auto mb-3 text-[var(--ll-muted)]" />
        <p class="text-sm text-[var(--ll-muted)]">{{ state === 'expired' ? t('files.share_expired') : t('files.share_invalid') }}</p>
      </Card>

      <Card v-else-if="state === 'locked'" :body-class="'p-6'" class="mx-auto max-w-sm">
        <h1 class="mb-1 text-lg font-semibold">{{ meta?.name ?? t('gallery.shared_album') }}</h1>
        <p class="mb-5 text-sm text-[var(--ll-muted)]">{{ t('files.share_password_prompt') }}</p>
        <div v-if="unlockError" class="mb-4 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-600 dark:text-red-400">{{ t('files.share_err_wrong_password') }}</div>
        <form class="flex items-end gap-2" @submit.prevent="unlock">
          <div class="flex-1"><TextField v-model="password" :label="t('auth_ui.password')" type="password" icon="lock" autocomplete="off" @enter="unlock" /></div>
          <Btn type="submit" variant="solid" :loading="unlocking">{{ t('files.share_unlock') }}</Btn>
        </form>
      </Card>

      <template v-else-if="state === 'ready' && manifest">
        <div class="mb-3 flex items-center gap-2">
          <h1 class="text-lg font-semibold">{{ manifest.name }}</h1>
          <Badge tone="gray">{{ manifest.photos.length }}</Badge>
        </div>
        <div v-if="!manifest.photos.length" class="py-16 text-center text-sm text-[var(--ll-muted)]">{{ t('files.share_empty') }}</div>
        <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
          <button v-for="(p, i) in manifest.photos" :key="p.id" class="aspect-square overflow-hidden rounded-lg bg-black/[0.06] dark:bg-white/10" @click="viewer = i">
            <img :src="thumbUrl(p.id)" loading="lazy" class="h-full w-full object-cover">
          </button>
        </div>
      </template>
    </div>

    <!-- Lightbox -->
    <div v-if="viewer >= 0 && manifest" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" @click.self="viewer = -1">
      <button class="absolute right-4 top-4 rounded-full p-2 text-white/80 hover:bg-white/10" @click="viewer = -1"><Icon name="close" :size="24" /></button>
      <button class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="step(-1)"><Icon name="chevron_left" :size="32" /></button>
      <button class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-2 text-white/80 hover:bg-white/10" @click="step(1)"><Icon name="chevron_right" :size="32" /></button>
      <img v-if="manifest.photos[viewer]" :src="previewUrl(manifest.photos[viewer].id)" class="max-h-[92vh] max-w-[92vw] object-contain">
      <a v-if="manifest.allowDownload && manifest.photos[viewer]" :href="rawUrl(manifest.photos[viewer].id, true)" download class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-4 py-2 text-sm text-white hover:bg-white/20">{{ t('common.download') }}</a>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon, Card, TextField, Btn, Badge } from '@spa/ui';
import { api, ApiError } from '@spa/api/client';

const BASE = (import.meta.env.VITE_API_URL as string | undefined)?.replace(/\/$/, '') ?? '';

interface Meta { found: boolean; name: string | null; count: number | null; needsPassword: boolean; unlocked: boolean; allowDownload: boolean; expiresAt: string | null }
interface Photo { id: number; name: string; media_type: string; taken_at: string | null }
interface Manifest { name: string; allowDownload: boolean; photos: Photo[] }

const route = useRoute();
const token = typeof route.params.token === 'string' ? route.params.token : String(route.params.token ?? '');

const loading = ref(true);
const state = ref<'invalid' | 'expired' | 'locked' | 'ready'>('invalid');
const meta = ref<Meta | null>(null);
const manifest = ref<Manifest | null>(null);
const grant = ref<string | null>(null);
const password = ref('');
const unlocking = ref(false);
const unlockError = ref(false);
const viewer = ref(-1);

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const m = await api.get<Meta>(`/api/v1/gallery-share/${encodeURIComponent(token)}`);
    if (!m.found) { state.value = 'invalid'; return; }
    meta.value = m;
    if (m.expiresAt && new Date(m.expiresAt).getTime() < Date.now()) { state.value = 'expired'; return; }
    if (m.needsPassword && !m.unlocked) { state.value = 'locked'; return; }
    await loadManifest();
  } catch {
    state.value = 'invalid';
  } finally {
    loading.value = false;
  }
}

async function loadManifest() {
  const headers = grant.value ? { 'X-Share-Grant': grant.value } : undefined;
  manifest.value = await api.get<Manifest>(`/api/v1/gallery-share/${encodeURIComponent(token)}/manifest`, headers);
  state.value = 'ready';
}

async function unlock() {
  if (!password.value) return;
  unlocking.value = true;
  unlockError.value = false;
  try {
    const r = await api.post<{ ok: boolean; grant: string }>(`/api/v1/gallery-share/${encodeURIComponent(token)}/unlock`, { password: password.value });
    grant.value = r.grant;
    await loadManifest();
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) unlockError.value = true;
    else state.value = 'invalid';
  } finally {
    unlocking.value = false;
  }
}

function photoUrl(id: number, kind: 'thumb' | 'preview' | 'raw', download = false): string {
  const params = new URLSearchParams();
  if (grant.value) params.set('grant', grant.value);
  if (download) params.set('download', '1');
  const q = params.toString();
  return `${BASE}/api/v1/gallery-share/${encodeURIComponent(token)}/photo/${id}/${kind}${q ? `?${q}` : ''}`;
}
const thumbUrl = (id: number) => photoUrl(id, 'thumb');
const previewUrl = (id: number) => photoUrl(id, 'preview');
const rawUrl = (id: number, download: boolean) => photoUrl(id, 'raw', download);

function step(d: number) {
  const n = manifest.value?.photos.length ?? 0;
  if (!n) { viewer.value = -1; return; }
  viewer.value = (viewer.value + d + n) % n;
}
</script>
