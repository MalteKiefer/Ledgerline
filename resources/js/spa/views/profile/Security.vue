<template>
  <div>
    <!-- Change password -->
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

    <!-- Two-factor authentication -->
    <v-card rounded="xl" border flat>
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
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiShieldCheck, mdiShieldOff, mdiRefresh, mdiContentCopy } from '@mdi/js';
import { useAuthStore } from '@spa/stores/auth';
import { useProfileStore } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';

const auth = useAuthStore();
const p = useProfileStore();
const { success, error } = useToast();

// --- Change password -------------------------------------------------------
const pw = reactive({ current: '', next: '', confirm: '' });
const pwErr = reactive<{ current_password?: string[]; password?: string[] }>({});
const pwBusy = ref(false);

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

onMounted(() => { void refresh2fa(); });

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
</script>

<style scoped>
.qr-box :deep(svg) { display: block; width: 160px; height: 160px; }
.recovery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px; font-family: monospace; font-size: 0.8rem; }
.secret-field { max-width: 320px; }
.secret-field :deep(input) { font-family: monospace; letter-spacing: 0.05em; }
</style>
