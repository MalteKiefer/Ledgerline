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
      <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Select v-model="tzModel" :label="t('account.pref_timezone')" :options="tzItems" @update:modelValue="onTimezone" />
        <Select v-model="p.prefs.date_format" :label="t('account.pref_date_format')" :options="dateFmtItems" @update:modelValue="savePref('date_format')" />
      </div>
    </template>
    <template v-if="p.prefs && auth.can('mail')">
      <div class="my-4 border-t border-[var(--ll-border)]" />
      <h3 class="mb-2 text-sm font-semibold">{{ t('account.mail_prefs_heading') }}</h3>
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
import { ref, computed, watch, onMounted } from 'vue';
import { trans as t, loadLanguageAsync } from 'laravel-vue-i18n';
import { persistLocale } from '@spa/plugins/i18n';
import { Card, Select } from '@spa/ui';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore, type DisplayPreferences } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';
import { timezoneList, browserTz } from '@spa/lib/datetime';

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

// Timezone: "" = follow the browser/system zone; else a hard IANA override.
const tzModel = ref('');
watch(() => p.prefs?.timezone, (v) => { tzModel.value = v ?? ''; }, { immediate: true });
const tzItems = computed(() => [
  { title: t('account.pref_timezone_auto', { tz: browserTz() }), value: '' },
  ...timezoneList().map((z) => ({ title: z, value: z })),
]);
async function onTimezone(v: string) {
  if (!p.prefs) return;
  p.prefs.timezone = v || null;
  await savePref('timezone');
}
// Date-format presets, each labelled with a live example of a fixed date.
const sample = '2026-03-09T00:00:00Z';
const dateFmtItems = computed(() => (['system', 'dmy', 'dmy_dot', 'mdy', 'ymd'] as const).map((f) => ({
  title: t(`account.date_format_${f}`) + ' — ' + fmtDateWith(f), value: f,
})));
function fmtDateWith(f: string): string {
  // Preview without mutating the active preference.
  const d = new Date(sample);
  if (f === 'system') return d.toLocaleDateString(document.documentElement.lang || 'en', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
  const y = '2026'; const m = '03'; const day = '09';
  return f === 'dmy' ? `${day}/${m}/${y}` : f === 'dmy_dot' ? `${day}.${m}.${y}` : f === 'mdy' ? `${m}/${day}/${y}` : `${y}-${m}-${day}`;
}

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
  persistLocale(v); // client-side persistence (standalone-independent)
  try { await p.setLocale(v); } catch { /* non-fatal */ }
}
</script>
