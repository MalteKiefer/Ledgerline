<template>
  <div class="min-h-screen bg-[var(--ll-bg)] px-4 py-10 text-[var(--ll-fg)]">
    <div class="mx-auto max-w-lg">
      <div
        class="rounded-2xl border border-[var(--ll-border)] bg-[var(--ll-card)] p-6 shadow-sm"
        @dragenter.prevent="drag++" @dragover.prevent @dragleave.prevent="drag = Math.max(0, drag - 1)" @drop.prevent="onDrop"
      >
        <div v-if="loading" class="py-16 text-center text-sm text-[var(--ll-muted)]">…</div>
        <div v-else-if="notFound" class="py-16 text-center">
          <Icon name="link_off" :size="40" class="text-[var(--ll-muted)]" />
          <p class="mt-2 text-sm text-[var(--ll-muted)]">{{ t('files.ul_invalid') }}</p>
        </div>
        <template v-else>
          <div class="mb-4 flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-xl bg-primary-500/15 text-primary-600 dark:text-primary-300"><Icon name="upload" :size="22" /></div>
            <div class="min-w-0">
              <h1 class="truncate text-lg font-semibold">{{ meta.label || t('files.ul_title') }}</h1>
              <p v-if="meta.owner" class="truncate text-xs text-[var(--ll-muted)]">{{ t('files.ul_from', { owner: meta.owner }) }}</p>
            </div>
          </div>

          <label
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors"
            :class="drag > 0 ? 'border-primary-500 bg-primary-500/10' : 'border-[var(--ll-border)] hover:bg-black/[0.02] dark:hover:bg-white/[0.03]'"
          >
            <input type="file" multiple class="hidden" @change="onPick" >
            <Icon name="cloud_upload" :size="32" class="text-[var(--ll-muted)]" />
            <span class="text-sm font-medium">{{ t('files.ul_drop') }}</span>
            <span class="text-xs text-[var(--ll-muted)]">{{ t('files.ul_or_click') }}</span>
          </label>

          <div v-if="items.length" class="mt-4 space-y-2">
            <div v-for="(it, i) in items" :key="i" class="text-sm">
              <div class="flex items-center gap-2">
                <Icon :name="it.done ? 'check_circle' : it.error ? 'error' : 'upload_file'" :size="16" :class="it.done ? 'text-green-600' : it.error ? 'text-red-600' : 'text-[var(--ll-muted)]'" />
                <span class="min-w-0 flex-1 truncate">{{ it.name }}</span>
                <span class="tabular-nums text-xs text-[var(--ll-muted)]">{{ it.error ? t('files.ul_failed') : it.done ? '✓' : Math.round(it.frac * 100) + '%' }}</span>
              </div>
              <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-black/[0.08] dark:bg-white/10">
                <div class="h-full rounded-full transition-all" :class="it.error ? 'bg-red-500' : 'bg-primary-500'" :style="{ width: (it.done ? 100 : it.frac * 100) + '%' }" />
              </div>
            </div>
          </div>
          <p v-if="anyDone" class="mt-4 text-center text-sm font-medium text-green-600 dark:text-green-400">{{ t('files.ul_thanks') }}</p>
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
import { useFilesStore } from '@spa/stores/files';

const route = useRoute();
const s = useFilesStore();
const token = String(route.params.token ?? '');

const loading = ref(true);
const notFound = ref(false);
const meta = reactive<{ label: string | null; owner: string }>({ label: null, owner: '' });
const drag = ref(0);
interface Item { name: string; frac: number; done: boolean; error: boolean }
const items = ref<Item[]>([]);
const anyDone = computed(() => items.value.some((i) => i.done));

onMounted(async () => {
  try { const m = await s.uploadLinkMeta(token); meta.label = m.label; meta.owner = m.owner; }
  catch { notFound.value = true; }
  finally { loading.value = false; }
});

async function send(files: File[]) {
  for (const f of files) {
    const it = reactive<Item>({ name: f.name, frac: 0, done: false, error: false });
    items.value.push(it);
    try { await s.uploadLinkSend(token, f, (fr) => { it.frac = fr; }); it.done = true; }
    catch { it.error = true; }
  }
}
function onPick(e: Event) {
  const list = (e.target as HTMLInputElement).files;
  if (list) void send(Array.from(list));
  (e.target as HTMLInputElement).value = '';
}
function onDrop(e: DragEvent) {
  drag.value = 0;
  const list = e.dataTransfer?.files;
  if (list && list.length) void send(Array.from(list));
}
</script>
