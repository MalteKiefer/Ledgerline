<template>
  <v-row>
    <v-col cols="12" md="8" offset-md="2">
      <h1 class="text-h5 mb-4">{{ t('pages.profile.title') }}</h1>

      <!-- Account -->
      <v-card rounded="xl" border flat class="mb-4">
        <v-card-title>{{ t('account.nav_account') }}</v-card-title>
        <v-card-text class="d-flex align-center ga-4">
          <v-avatar size="64" color="primary">
            <v-img v-if="avatarUrl" :src="avatarUrl" />
            <span v-else class="text-h6">{{ initials }}</span>
          </v-avatar>
          <div class="flex-grow-1">
            <div class="text-body-1 font-weight-medium">{{ auth.user?.name }}</div>
            <div class="text-medium-emphasis">{{ auth.user?.email }}</div>
          </div>
          <v-btn variant="tonal" :prepend-icon="mdiUpload" @click="pickAvatar">{{ t('pages.profile.avatar_change') }}</v-btn>
          <v-btn v-if="avatarUrl" variant="text" color="error" @click="onRemoveAvatar">{{ t('common.delete') }}</v-btn>
          <input ref="avatarInput" type="file" accept="image/*" class="d-none" @change="onAvatar" >
        </v-card-text>
      </v-card>

      <!-- Appearance -->
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

      <!-- Security: change password -->
      <v-card rounded="xl" border flat class="mb-4">
        <v-card-title>{{ t('account.password_title') }}</v-card-title>
        <v-card-text>
          <v-form @submit.prevent="onPassword">
            <v-text-field v-model="pw.current" :label="t('account.password_current')" type="password" variant="outlined" density="comfortable" :error-messages="pwErr.current_password" />
            <v-text-field v-model="pw.next" :label="t('account.password_new')" type="password" variant="outlined" density="comfortable" :error-messages="pwErr.password" />
            <v-text-field v-model="pw.confirm" :label="t('account.password_confirm')" type="password" variant="outlined" density="comfortable" />
            <v-btn type="submit" color="primary" :loading="pwBusy">{{ t('common.save') }}</v-btn>
          </v-form>
        </v-card-text>
      </v-card>

      <!-- Devices -->
      <v-card rounded="xl" border flat class="mb-4">
        <v-card-title>{{ t('account.devices_list_heading') }}</v-card-title>
        <v-list>
          <v-list-item v-for="d in p.devices" :key="d.id" :title="d.name" :subtitle="deviceSub(d)">
            <template #append>
              <v-chip v-if="d.wipe_pending" size="small" color="warning" class="mr-2">wipe</v-chip>
              <v-btn variant="text" size="small" :icon="mdiCellphoneRemove" @click="p.wipeDevice(d.id)" />
              <v-btn variant="text" size="small" color="error" :icon="mdiDelete" @click="p.revokeDevice(d.id)" />
            </template>
          </v-list-item>
          <v-list-item v-if="!p.devices.length" :title="t('common.none')" class="text-medium-emphasis" />
        </v-list>
      </v-card>

      <!-- Data / danger -->
      <v-card rounded="xl" border flat>
        <v-card-title>{{ t('account.export_heading') }}</v-card-title>
        <v-card-text class="d-flex ga-2 flex-wrap">
          <v-btn variant="tonal" :prepend-icon="mdiDownload" href="/api/v1/account/export">{{ t('account.export_button') }}</v-btn>
          <v-spacer />
          <v-btn variant="text" color="error" :prepend-icon="mdiDeleteAlert" @click="confirmDelete = true">{{ t('account.delete_button') }}</v-btn>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-dialog v-model="confirmDelete" max-width="480">
    <v-card rounded="xl">
      <v-card-title>{{ t('account.delete_button') }}</v-card-title>
      <v-card-text>
        <p class="mb-3">{{ t('account.delete_modal_warning') }}</p>
        <v-text-field v-model="delPassword" :label="t('account.password_title')" type="password" variant="outlined" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDelete = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="error" :loading="delBusy" @click="onDelete">{{ t('common.delete') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useTheme } from 'vuetify';
import { useRouter } from 'vue-router';
import { trans as t, loadLanguageAsync } from 'laravel-vue-i18n';
import { mdiUpload, mdiDownload, mdiDelete, mdiDeleteAlert, mdiCellphoneRemove } from '@mdi/js';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore, type DisplayPreferences, type DeviceToken } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const p = useProfileStore();
const theme = useTheme();
const router = useRouter();
const { success, error } = useToast();

const avatarBust = ref(0);
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarUrl = computed(() => (auth.user?.has_avatar ? `/api/v1/avatar?v=${avatarBust.value}` : ''));
const initials = computed(() => (auth.user?.name ?? '?').slice(0, 1).toUpperCase());

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

const pw = reactive({ current: '', next: '', confirm: '' });
const pwErr = reactive<{ current_password?: string[]; password?: string[] }>({});
const pwBusy = ref(false);

const confirmDelete = ref(false);
const delPassword = ref('');
const delBusy = ref(false);

onMounted(async () => {
  await Promise.all([p.loadPrefs(), p.loadDevices()]);
});

function deviceSub(d: DeviceToken): string {
  return [d.last_ip, d.version, d.last_used_at].filter(Boolean).join(' · ');
}

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

function pickAvatar() { avatarInput.value?.click(); }

async function onAvatar(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  try {
    await p.uploadAvatar(file);
    if (auth.user) auth.user.has_avatar = true;
    avatarBust.value++;
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  }
}

async function onRemoveAvatar() {
  await p.removeAvatar();
  if (auth.user) auth.user.has_avatar = false;
}

async function onPassword() {
  pwBusy.value = true;
  pwErr.current_password = undefined;
  pwErr.password = undefined;
  try {
    await p.changePassword(pw.current, pw.next, pw.confirm);
    pw.current = pw.next = pw.confirm = '';
    success(t('common.saved'));
  } catch (e) {
    if (e instanceof ApiError && e.fields) {
      pwErr.current_password = e.fields.current_password;
      pwErr.password = e.fields.password;
    } else {
      error(t('common.error'));
    }
  } finally {
    pwBusy.value = false;
  }
}

async function onDelete() {
  delBusy.value = true;
  try {
    await p.deleteAccount(delPassword.value);
    await auth.logout();
    router.push({ name: 'login' });
  } catch {
    error(t('common.error'));
  } finally {
    delBusy.value = false;
  }
}
</script>
