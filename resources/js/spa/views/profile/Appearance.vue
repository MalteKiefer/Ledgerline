<template>
  <v-card rounded="xl" border flat class="mb-4">
    <v-card-title>{{ t('account.appearance_heading') }}</v-card-title>
    <v-card-text>
      <v-row dense>
        <v-col cols="12" sm="6">
          <v-select :label="t('account.appearance_theme')" v-model="themeChoice" :items="themeItems" variant="outlined" density="comfortable" @update:model-value="onTheme" />
        </v-col>
        <v-col cols="12" sm="6">
          <v-select :label="t('account.appearance_language')" v-model="localeChoice" :items="localeItems" variant="outlined" density="comfortable" @update:model-value="onLocale" />
        </v-col>
      </v-row>
      <template v-if="p.prefs">
        <v-divider class="my-2" />
        <v-row dense>
          <v-col cols="6" sm="4"><v-select :label="t('account.pref_distance')" v-model="p.prefs.unit_distance" :items="['km','mi']" variant="outlined" density="compact" @update:model-value="savePref('unit_distance')" /></v-col>
          <v-col cols="6" sm="4"><v-select :label="t('account.pref_elevation')" v-model="p.prefs.unit_elevation" :items="['m','ft']" variant="outlined" density="compact" @update:model-value="savePref('unit_elevation')" /></v-col>
          <v-col cols="6" sm="4"><v-select :label="t('account.pref_weight')" v-model="p.prefs.unit_weight" :items="['kg','lb']" variant="outlined" density="compact" @update:model-value="savePref('unit_weight')" /></v-col>
          <v-col cols="6" sm="4"><v-select :label="t('account.pref_temp')" v-model="p.prefs.unit_temp" :items="['c','f']" variant="outlined" density="compact" @update:model-value="savePref('unit_temp')" /></v-col>
          <v-col cols="6" sm="4"><v-select :label="t('account.pref_glucose')" v-model="p.prefs.unit_glucose" :items="['mgdl','mmoll']" variant="outlined" density="compact" @update:model-value="savePref('unit_glucose')" /></v-col>
          <v-col cols="6" sm="4"><v-select :label="t('account.pref_time')" v-model="p.prefs.time_format" :items="['24h','12h']" variant="outlined" density="compact" @update:model-value="savePref('time_format')" /></v-col>
        </v-row>
      </template>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useTheme } from 'vuetify';
import { trans as t, loadLanguageAsync } from 'laravel-vue-i18n';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore, type DisplayPreferences } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';

const auth = useAuthStore();
const p = useProfileStore();
const theme = useTheme();
const { success, error } = useToast();

const themeItems = [
  { title: t('messages.menu.theme_light'), value: 'light' },
  { title: t('messages.menu.theme_dark'), value: 'dark' },
];
const themeChoice = ref(theme.global.current.value.dark ? 'dark' : 'light');
const localeItems = [
  { title: 'Deutsch', value: 'de' },
  { title: 'English', value: 'en' },
  { title: 'Русский', value: 'ru' },
];
const localeChoice = ref(auth.user?.locale ?? 'en');

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
  theme.global.name.value = v;
  try { await p.setTheme(v as 'light' | 'dark'); } catch { /* non-fatal */ }
}

async function onLocale(v: string) {
  await loadLanguageAsync(v);
  try { await p.setLocale(v); } catch { /* non-fatal */ }
}
</script>
