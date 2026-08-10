<template>
  <Card :title="t('account.appearance_heading')">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Select v-model="themeChoice" :label="t('account.appearance_theme')" :options="themeItems" @update:modelValue="onTheme" />
      <Select v-model="localeChoice" :label="t('account.appearance_language')" :options="localeItems" @update:modelValue="onLocale" />
    </div>
    <template v-if="p.prefs">
      <div class="my-4 border-t border-[var(--ll-border)]" />
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <Select v-model="p.prefs.unit_distance" :label="t('account.pref_distance')" :options="opts(['km', 'mi'])" @update:modelValue="savePref('unit_distance')" />
        <Select v-model="p.prefs.unit_elevation" :label="t('account.pref_elevation')" :options="opts(['m', 'ft'])" @update:modelValue="savePref('unit_elevation')" />
        <Select v-model="p.prefs.unit_weight" :label="t('account.pref_weight')" :options="opts(['kg', 'lb'])" @update:modelValue="savePref('unit_weight')" />
        <Select v-model="p.prefs.unit_temp" :label="t('account.pref_temp')" :options="opts(['c', 'f'])" @update:modelValue="savePref('unit_temp')" />
        <Select v-model="p.prefs.unit_glucose" :label="t('account.pref_glucose')" :options="opts(['mgdl', 'mmoll'])" @update:modelValue="savePref('unit_glucose')" />
        <Select v-model="p.prefs.time_format" :label="t('account.pref_time')" :options="opts(['24h', '12h'])" @update:modelValue="savePref('time_format')" />
      </div>
    </template>
    <template v-if="p.prefs && auth.can('mail')">
      <div class="my-4 border-t border-[var(--ll-border)]" />
      <h3 class="mb-2 text-sm font-semibold">{{ t('account.mail_prefs_heading') }}</h3>
      <label class="mb-3 flex items-center gap-3 text-sm">
        <input v-model="p.prefs.mail_load_remote" type="checkbox" class="accent-primary-500" @change="savePref('mail_load_remote')" >
        {{ t('account.mail_load_remote') }}
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-[var(--ll-muted)]">{{ t('account.mail_signature') }}</span>
        <textarea
          v-model="p.prefs.mail_signature" rows="3"
          class="w-full rounded-lg border border-[var(--ll-border)] bg-transparent px-3 py-2 text-sm"
          @change="savePref('mail_signature')"
        />
      </label>
    </template>
  </Card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { trans as t, loadLanguageAsync } from 'laravel-vue-i18n';
import { Card, Select } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore, type DisplayPreferences } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';

const auth = useAuthStore();
const p = useProfileStore();
const { success, error } = useToast();

const themeItems = [
  { title: t('messages.menu.theme_light'), value: 'light' },
  { title: t('messages.menu.theme_dark'), value: 'dark' },
];
const themeChoice = ref(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
const localeItems = [
  { title: 'Deutsch', value: 'de' },
  { title: 'English', value: 'en' },
  { title: 'Русский', value: 'ru' },
];
const localeChoice = ref(auth.user?.locale ?? 'en');

function opts(values: string[]) { return values.map((v) => ({ title: v, value: v })); }

onMounted(() => { if (!p.prefs) void p.loadPrefs(); });

async function savePref(key: keyof DisplayPreferences) {
  if (!p.prefs) return;
  try {
    await p.savePrefs({ [key]: p.prefs[key] } as Partial<DisplayPreferences>);
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  }
}

async function onTheme(v: string) {
  document.documentElement.classList.toggle('dark', v === 'dark');
  localStorage.setItem('ll_theme', v === 'dark' ? 'dark' : 'light');
  try { await p.setTheme(v as 'light' | 'dark'); } catch { /* non-fatal */ }
}

async function onLocale(v: string) {
  await loadLanguageAsync(v);
  try { await p.setLocale(v); } catch { /* non-fatal */ }
}
</script>
