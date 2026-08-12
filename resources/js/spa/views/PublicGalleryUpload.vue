<template>
  <div class="min-h-screen bg-[var(--ll-bg)] px-4 py-10 text-[var(--ll-fg)]">
    <div class="mx-auto max-w-lg">
      <div class="rounded-2xl border border-[var(--ll-border)] bg-[var(--ll-elevated)] p-6 shadow-sm"
        @dragenter.prevent="drag++" @dragover.prevent @dragleave.prevent="drag = Math.max(0, drag - 1)" @drop.prevent="onDrop">
        <div v-if="loading" class="py-16 text-center text-sm text-[var(--ll-muted)]">…</div>
        <div v-else-if="notFound" class="py-16 text-center">
          <Icon name="link_off" :size="40" class="text-[var(--ll-muted)]" />
          <p class="mt-2 text-sm text-[var(--ll-muted)]">{{ t('gallery.ul_invalid') }}</p>
        </div>
        <template v-else>
          <div class="mb-4 flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-xl bg-primary-500/15 text-primary-600 dark:text-primary-300"><Icon name="add_photo_alternate" :size="22" /></div>
            <div class="min-w-0">
              <h1 class="truncate text-lg font-semibold">{{ meta.label || meta.album || t('gallery.ul_title') }}</h1>
              <p v-if="meta.album" class="truncate text-xs text-[var(--ll-muted)]">{{ meta.album }}</p>
            </div>
          </div>
          <div v-if="meta.needs_password" class="mb-3">
            <label class="mb-1 block text-xs font-medium text-[var(--ll-muted)]">{{ t('gallery.ul_password') }}</label>
            <input v-model="password" type="password" autocomplete="off" class="w-full rounded-lg border bg-transparent px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/40" :class="pwError ? 'border-red-400' : 'border-[var(--ll-border)]'">
            <p v-if="pwError" class="mt-1 text-xs text-red-500">{{ t('gallery.ul_password_wrong') }}</p>
          </div>
          <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors"
            :class="[drag > 0 ? 'border-primary-500 bg-primary-500/10' : 'border-[var(--ll-border)] hover:bg-black/[0.02] dark:hover:bg-white/[0.03]', canUpload ? 'cursor-pointer' : 'cursor-not-allowed opacity-50']">
            <input type="file" accept="image/*,video/*" multiple class="hidden" :disabled="!canUpload" @change="onPick">
            <Icon name="cloud_upload" :size="32" class="text-[var(--ll-muted)]" />
            <span class="text-sm font-medium">{{ t('gallery.ul_drop') }}</span>
          </label>
          <div v-if="items.length" class="mt-4 space-y-2">
            <div v-for="(it, i) in items" :key="i" class="flex items-center gap-2 text-sm">
              <Icon :name="it.done ? 'check_circle' : it.error ? 'error' : 'upload_file'" :size="16" :class="it.done ? 'text-green-600' : it.error ? 'text-red-600' : 'text-[var(--ll-muted)]'" />
              <span class="min-w-0 flex-1 truncate">{{ it.name }}</span>
              <span class="tabular-nums text-xs text-[var(--ll-muted)]">{{ it.error ? '✗' : it.done ? '✓' : Math.round(it.frac * 100) + '%' }}</span>
            </div>
          </div>
          <p v-if="anyDone" class="mt-4 text-center text-sm font-medium text-green-600 dark:text-green-400">{{ t('gallery.ul_thanks') }}</p>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { trans as t } from 'laravel-vue-i18n';
import { Icon } from '@spa/ui';
import { ApiError } from '@spa/api/client';
import { useGalleryStore } from '@spa/stores/gallery';

const route = useRoute();
const g = useGalleryStore();
const token = String(route.params.token ?? '');
const loading = ref(true);
const notFound = ref(false);
const meta = reactive<{ label: string | null; album: string | null; needs_password: boolean }>({ label: null, album: null, needs_password: false });
const drag = ref(0);
const password = ref('');
const pwError = ref(false);
const canUpload = computed(() => !meta.needs_password || password.value.length > 0);
interface Item { name: string; frac: number; done: boolean; error: boolean }
const items = ref<Item[]>([]);
const anyDone = computed(() => items.value.some((i) => i.done));

onMounted(async () => {
  try { const m = await g.publicUploadMeta(token); meta.label = m.label; meta.album = m.album; meta.needs_password = m.needs_password; }
  catch { notFound.value = true; }
  finally { loading.value = false; }
});
async function send(files: File[]) {
  if (!canUpload.value) return;
  for (const f of files) {
    const it = reactive<Item>({ name: f.name, frac: 0, done: false, error: false });
    items.value.push(it);
    try { await g.publicUploadSend(token, f, (fr) => { it.frac = fr; }, password.value || undefined); it.done = true; pwError.value = false; }
    catch (e) { it.error = true; if (e instanceof ApiError && e.status === 403) pwError.value = true; }
  }
}
function onPick(e: Event) { const l = (e.target as HTMLInputElement).files; if (l) void send(Array.from(l)); (e.target as HTMLInputElement).value = ''; }
function onDrop(e: DragEvent) { drag.value = 0; if (!canUpload.value) return; const l = e.dataTransfer?.files; if (l && l.length) void send(Array.from(l)); }
</script>
