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

      <!-- Security: two-factor authentication -->
      <v-card rounded="xl" border flat class="mb-4">
        <v-card-title class="d-flex align-center ga-2">
          <v-icon :icon="mdiShieldCheck" size="small" />
          <span>{{ t('account.twofa_title') }}</span>
          <v-spacer />
          <v-chip size="small" :color="twofa.status === 'on' ? 'success' : undefined" label>
            {{ twofa.status === 'on' ? t('account.twofa_on') : t('account.twofa_off') }}
          </v-chip>
        </v-card-title>
        <v-card-text>
          <p class="text-medium-emphasis mb-4">{{ t('account.twofa_desc') }}</p>

          <!-- Disabled → offer to enable -->
          <template v-if="twofa.status === 'off'">
            <v-btn variant="tonal" color="primary" :prepend-icon="mdiShieldCheck" :loading="twofa.busy" @click="onEnable">
              {{ t('account.twofa_enable') }}
            </v-btn>
          </template>

          <!-- Pending: secret generated, awaiting confirmation -->
          <template v-else-if="twofa.status === 'pending'">
            <p class="mb-3">{{ t('account.twofa_scan') }}</p>
            <!-- Trusted server-generated (Fortify/BaconQrCode) SVG markup. -->
            <div v-if="twofa.qr?.svg" class="d-inline-block bg-white rounded-lg pa-3 mb-3 qr-box" v-html="twofa.qr.svg" />
            <v-text-field v-if="twofa.qr?.secret" :model-value="twofa.qr.secret" readonly variant="outlined" density="compact" class="mb-3 secret-field" label="secret" hide-details />
            <v-form class="d-flex flex-wrap align-start ga-2" @submit.prevent="onConfirm">
              <v-text-field
                v-model="twofa.code"
                :label="t('account.twofa_code')"
                inputmode="numeric"
                autocomplete="one-time-code"
                variant="outlined"
                density="comfortable"
                style="max-width: 200px"
                :error-messages="twofa.codeErr"
              />
              <v-btn type="submit" color="primary" :loading="twofa.busy">{{ t('account.twofa_confirm') }}</v-btn>
              <v-btn variant="text" :disabled="twofa.busy" @click="onCancelPending">{{ t('account.twofa_cancel') }}</v-btn>
            </v-form>
          </template>

          <!-- Enabled: recovery codes + disable -->
          <template v-else>
            <div class="rounded-lg border pa-3 mb-4">
              <p class="text-caption text-medium-emphasis mb-2">{{ t('account.twofa_recovery_codes') }}</p>
              <div v-if="twofa.recovery.length" class="recovery-grid mb-3">
                <code v-for="c in twofa.recovery" :key="c">{{ c }}</code>
              </div>
              <div class="d-flex ga-2">
                <v-btn variant="tonal" size="small" :prepend-icon="mdiRefresh" :loading="twofa.busy" @click="onRegenerate">
                  {{ t('account.twofa_regenerate') }}
                </v-btn>
                <v-btn v-if="twofa.recovery.length" variant="text" size="small" :prepend-icon="mdiContentCopy" @click="copyRecovery">
                  {{ t('account.cli_copy') }}
                </v-btn>
              </div>
            </div>
            <v-btn variant="tonal" color="error" :prepend-icon="mdiShieldOff" :loading="twofa.busy" @click="onDisable">
              {{ t('account.twofa_disable') }}
            </v-btn>
          </template>
        </v-card-text>
      </v-card>

      <!-- Mobile app: QR device pairing -->
      <v-card rounded="xl" border flat class="mb-4">
        <v-card-title>{{ t('account.devices_heading') }}</v-card-title>
        <v-card-text>
          <p class="text-medium-emphasis mb-4">{{ t('account.devices_hint') }}</p>

          <v-btn v-if="!pairing.active" variant="tonal" color="primary" :prepend-icon="mdiQrcode" :loading="pairing.busy" @click="onStartPairing">
            {{ t('account.devices_connect') }}
          </v-btn>

          <template v-else>
            <div v-if="pairing.status === 'pending_scan' || pairing.status === 'pending_approval'" class="d-flex flex-column flex-sm-row align-start ga-4">
              <v-img v-if="pairing.qr" :src="pairing.qr" width="160" height="160" class="flex-grow-0 rounded-lg border bg-white pa-1" cover />
              <div>
                <p v-if="pairing.status === 'pending_scan'" class="text-medium-emphasis">{{ t('account.devices_scan_hint') }}</p>
                <div v-else>
                  <p class="mb-2">{{ t('account.devices_approve_q') }} “{{ pairing.deviceName }}”?</p>
                  <div class="d-flex ga-2">
                    <v-btn color="primary" :loading="pairing.busy" @click="onApprovePairing">{{ t('account.devices_allow') }}</v-btn>
                    <v-btn variant="tonal" :disabled="pairing.busy" @click="onRejectPairing">{{ t('account.devices_deny') }}</v-btn>
                  </div>
                </div>
              </div>
            </div>
            <div v-else>
              <p v-if="pairing.status === 'approved' || pairing.status === 'consumed'" class="text-success font-weight-medium">{{ t('account.devices_connected') }}</p>
              <p v-else-if="pairing.status === 'rejected'" class="text-medium-emphasis">{{ t('account.devices_rejected') }}</p>
              <p v-else class="text-medium-emphasis">{{ t('account.devices_expired') }}</p>
              <v-btn variant="text" class="mt-2 px-0" @click="resetPairing">{{ t('account.devices_again') }}</v-btn>
            </div>
          </template>
        </v-card-text>
      </v-card>

      <!-- Devices -->
      <v-card rounded="xl" border flat class="mb-4">
        <v-card-title>{{ t('account.devices_list_heading') }}</v-card-title>
        <v-list>
          <v-list-item v-for="d in p.devices" :key="d.id" :title="d.name" :subtitle="deviceSub(d)">
            <template #prepend><v-avatar size="34" variant="tonal" :color="d.current ? 'primary' : undefined"><span class="msym" style="font-size:18px">smartphone</span></v-avatar></template>
            <template #append>
              <v-chip v-if="d.current" size="x-small" color="primary" variant="tonal" class="mr-2">{{ t('account.sessions_current') }}</v-chip>
              <v-chip v-if="d.wipeRequested" size="small" color="warning" variant="tonal" class="mr-2">{{ t('account.devices_wipe_pending') }}</v-chip>
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

  <!-- Delete account: type the account email to confirm -->
  <v-dialog v-model="confirmDelete" max-width="480">
    <v-card rounded="xl">
      <v-card-title>{{ t('account.delete_modal_title') }}</v-card-title>
      <v-card-text>
        <p class="mb-3">{{ t('account.delete_modal_warning') }}</p>
        <v-text-field
          v-model="delConfirm"
          :label="t('account.delete_confirm_label')"
          :error-messages="delMismatch ? [t('account.delete_confirm_mismatch')] : []"
          variant="outlined"
          autocomplete="off"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDelete = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="error" :loading="delBusy" :disabled="!deleteReady" @click="onDelete">{{ t('account.delete_button') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Password step-up (enable / disable / regenerate recovery codes) -->
  <v-dialog v-model="pwPrompt.show" max-width="420" persistent>
    <v-card rounded="xl">
      <v-card-title>{{ t('account.password_current') }}</v-card-title>
      <v-card-text>
        <v-form @submit.prevent="submitPassword">
          <v-text-field
            v-model="pwPrompt.value"
            :label="t('account.password_current')"
            type="password"
            autocomplete="current-password"
            variant="outlined"
            autofocus
          />
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="cancelPassword">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :disabled="!pwPrompt.value" @click="submitPassword">{{ t('common.confirm') }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useTheme } from 'vuetify';
import { useRouter } from 'vue-router';
import { trans as t, loadLanguageAsync } from 'laravel-vue-i18n';
import {
  mdiUpload, mdiDownload, mdiDelete, mdiDeleteAlert, mdiCellphoneRemove,
  mdiShieldCheck, mdiShieldOff, mdiQrcode, mdiRefresh, mdiContentCopy,
} from '@mdi/js';
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
const delConfirm = ref('');
const delBusy = ref(false);
const deleteReady = computed(() => delConfirm.value.trim().toLowerCase() === (auth.user?.email ?? '').toLowerCase() && !!auth.user?.email);
const delMismatch = computed(() => delConfirm.value.length > 0 && !deleteReady.value);

// --- Two-factor ------------------------------------------------------------
const twofa = reactive<{
  status: 'off' | 'pending' | 'on';
  qr: { svg?: string; secret?: string; uri?: string } | null;
  code: string;
  codeErr: string[] | undefined;
  recovery: string[];
  busy: boolean;
}>({ status: 'off', qr: null, code: '', codeErr: undefined, recovery: [], busy: false });
// Current password captured for the enable→confirm window, so confirmation can
// reveal recovery codes without re-prompting. Cleared once enrollment settles.
const enrollPw = ref('');

// --- Device pairing --------------------------------------------------------
const pairing = reactive<{ active: boolean; id: number; qr: string; status: string; deviceName: string | null; busy: boolean }>(
  { active: false, id: 0, qr: '', status: '', deviceName: null, busy: false },
);
let pollTimer: ReturnType<typeof setTimeout> | undefined;

// --- Password step-up prompt ----------------------------------------------
const pwPrompt = reactive({ show: false, value: '' });
let pwResolve: ((v: string | null) => void) | null = null;
function askPassword(): Promise<string | null> {
  pwPrompt.value = '';
  pwPrompt.show = true;
  return new Promise((resolve) => { pwResolve = resolve; });
}
function submitPassword() {
  const v = pwPrompt.value;
  pwPrompt.show = false;
  pwPrompt.value = '';
  pwResolve?.(v);
  pwResolve = null;
}
function cancelPassword() {
  pwPrompt.show = false;
  pwPrompt.value = '';
  pwResolve?.(null);
  pwResolve = null;
}

function stepUpMessage(e: unknown): string {
  if (e instanceof ApiError && e.fields?.current_password?.length) return e.fields.current_password[0];
  return t('common.error');
}

onMounted(async () => {
  await Promise.all([p.loadPrefs(), p.loadDevices(), refresh2fa()]);
});

onUnmounted(() => clearTimeout(pollTimer));

function deviceSub(d: DeviceToken): string {
  return [d.meta, d.version].filter(Boolean).join(' · ');
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

// --- Two-factor handlers ---------------------------------------------------
async function refresh2fa() {
  // Authoritative confirmed-state now comes from /me (auth.user.two_factor).
  if (auth.user?.two_factor) { twofa.status = 'on'; return; }
  try {
    const s = await p.twoFactorState();
    if (s.pending) {
      twofa.status = 'pending';
      twofa.qr = { svg: s.svg, secret: s.secret, uri: s.uri };
    } else {
      twofa.status = 'off';
    }
  } catch {
    twofa.status = 'off';
  }
}

async function onEnable() {
  const password = await askPassword();
  if (password === null) return;
  twofa.busy = true;
  try {
    const s = await p.enable2fa(password);
    if (s.pending) {
      enrollPw.value = password;
      twofa.qr = { svg: s.svg, secret: s.secret, uri: s.uri };
      twofa.code = '';
      twofa.codeErr = undefined;
      twofa.status = 'pending';
    } else {
      // No QR came back → the account was already confirmed.
      twofa.status = 'on';
      twofa.qr = null;
    }
  } catch (e) {
    error(stepUpMessage(e));
  } finally {
    twofa.busy = false;
  }
}

async function onConfirm() {
  twofa.busy = true;
  twofa.codeErr = undefined;
  try {
    await p.confirm2fa(twofa.code.trim());
    twofa.status = 'on';
    twofa.qr = null;
    twofa.code = '';
    success(t('account.twofa_enabled'));
    // Reveal recovery codes using the password entered when enabling.
    if (enrollPw.value) {
      try { twofa.recovery = await p.regenerateRecovery(enrollPw.value); } catch { /* codes stay hidden until regenerate */ }
    }
    enrollPw.value = '';
  } catch (e) {
    if (e instanceof ApiError && e.fields?.code?.length) twofa.codeErr = e.fields.code;
    else error(t('common.error'));
  } finally {
    twofa.busy = false;
  }
}

async function onCancelPending() {
  const password = enrollPw.value || await askPassword();
  if (!password) return;
  twofa.busy = true;
  try {
    await p.disable2fa(password);
    twofa.status = 'off';
    twofa.qr = null;
    twofa.code = '';
    enrollPw.value = '';
  } catch (e) {
    error(stepUpMessage(e));
  } finally {
    twofa.busy = false;
  }
}

async function onRegenerate() {
  const password = await askPassword();
  if (password === null) return;
  twofa.busy = true;
  try {
    twofa.recovery = await p.regenerateRecovery(password);
    success(t('account.twofa_recovery_regenerated'));
  } catch (e) {
    error(stepUpMessage(e));
  } finally {
    twofa.busy = false;
  }
}

async function onDisable() {
  const password = await askPassword();
  if (password === null) return;
  twofa.busy = true;
  try {
    await p.disable2fa(password);
    twofa.status = 'off';
    twofa.qr = null;
    twofa.recovery = [];
    twofa.code = '';
    enrollPw.value = '';
    success(t('account.twofa_disabled'));
  } catch (e) {
    error(stepUpMessage(e));
  } finally {
    twofa.busy = false;
  }
}

async function copyRecovery() {
  try {
    await navigator.clipboard.writeText(twofa.recovery.join('\n'));
    success(t('common.copied'));
  } catch {
    error(t('common.error'));
  }
}

// --- Device pairing handlers ----------------------------------------------
async function onStartPairing() {
  pairing.busy = true;
  try {
    const s = await p.startPairing();
    pairing.id = s.id;
    pairing.qr = s.qr;
    pairing.status = 'pending_scan';
    pairing.deviceName = null;
    pairing.active = true;
    pollPairing();
  } catch (e) {
    error(e instanceof ApiError && e.status === 429 ? t('account.pair_rate_limited') : t('account.pair_start_failed'));
  } finally {
    pairing.busy = false;
  }
}

function pollPairing() {
  clearTimeout(pollTimer);
  pollTimer = setTimeout(async () => {
    if (!pairing.active) return;
    try {
      const s = await p.pairingStatus(pairing.id);
      pairing.status = s.status;
      pairing.deviceName = s.device_name;
      if (['approved', 'consumed', 'rejected', 'expired'].includes(s.status)) {
        if (s.status === 'approved' || s.status === 'consumed') await p.loadDevices();
        return;
      }
    } catch { /* transient — keep polling */ }
    pollPairing();
  }, 2000);
}

async function onApprovePairing() {
  pairing.busy = true;
  try {
    const r = await p.approvePairing(pairing.id);
    pairing.status = r.status;
    success(t('account.devices_connected'));
    await p.loadDevices();
  } catch {
    error(t('common.error'));
  } finally {
    pairing.busy = false;
  }
}

async function onRejectPairing() {
  pairing.busy = true;
  try {
    const r = await p.rejectPairing(pairing.id);
    pairing.status = r.status;
  } catch {
    error(t('common.error'));
  } finally {
    pairing.busy = false;
  }
}

function resetPairing() {
  clearTimeout(pollTimer);
  pairing.active = false;
  pairing.qr = '';
  pairing.status = '';
  pairing.id = 0;
  pairing.deviceName = null;
}

async function onDelete() {
  if (!deleteReady.value) return;
  delBusy.value = true;
  try {
    await p.deleteAccount(delConfirm.value.trim());
    await auth.logout();
    router.push({ name: 'login' });
  } catch {
    error(t('common.error'));
  } finally {
    delBusy.value = false;
  }
}
</script>

<style scoped>
.qr-box :deep(svg) { display: block; width: 160px; height: 160px; }
.recovery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; font-family: monospace; font-size: 0.8rem; }
.secret-field { max-width: 320px; }
.secret-field :deep(input) { font-family: monospace; letter-spacing: 0.05em; }
</style>
