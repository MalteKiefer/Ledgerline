<template>
  <div>
    <!-- Change password -->
    <Card :title="t('account.password_title')" class="mb-4">
      <form class="space-y-4" @submit.prevent="onPassword">
        <TextField v-model="pw.current" :label="t('account.password_current')" type="password" :error="pwErr.current_password?.[0] ?? ''" />
        <TextField v-model="pw.next" :label="t('account.password_new')" type="password" :error="pwErr.password?.[0] ?? ''" />
        <TextField v-model="pw.confirm" :label="t('account.password_confirm')" type="password" />
        <Btn type="submit" variant="solid" :loading="pwBusy">{{ t('common.save') }}</Btn>
      </form>
    </Card>

    <!-- Two-factor authentication -->
    <Card>
      <template #header>
        <Icon name="security" :size="18" />
        <span class="text-sm font-semibold">{{ t('account.twofa_title') }}</span>
      </template>
      <template #actions>
        <Badge :tone="twofa.status === 'on' ? 'success' : 'gray'">
          {{ twofa.status === 'on' ? t('account.twofa_on') : t('account.twofa_off') }}
        </Badge>
      </template>

      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('account.twofa_desc') }}</p>

      <!-- Disabled → offer to enable -->
      <template v-if="twofa.status === 'off'">
        <Btn variant="soft" icon="security" :loading="twofa.busy" @click="onEnable">
          {{ t('account.twofa_enable') }}
        </Btn>
      </template>

      <!-- Pending: secret generated, awaiting confirmation -->
      <template v-else-if="twofa.status === 'pending'">
        <p class="mb-3 text-sm">{{ t('account.twofa_scan') }}</p>
        <!-- Trusted server-generated (Fortify/BaconQrCode) SVG markup. -->
        <div v-if="twofa.qr?.svg" class="mb-3 inline-block rounded-lg bg-white p-3 [&_svg]:block [&_svg]:h-40 [&_svg]:w-40" v-html="twofa.qr.svg" />
        <TextField v-if="twofa.qr?.secret" class="mb-3 max-w-xs font-mono" :model-value="twofa.qr.secret" label="secret" disabled />
        <form class="flex flex-wrap items-start gap-2" @submit.prevent="onConfirm">
          <TextField
            v-model="twofa.code"
            class="max-w-[200px]"
            :label="t('account.twofa_code')"
            inputmode="numeric"
            autocomplete="one-time-code"
            :error="twofa.codeErr?.[0] ?? ''"
          />
          <Btn type="submit" variant="solid" :loading="twofa.busy">{{ t('account.twofa_confirm') }}</Btn>
          <Btn variant="ghost" :disabled="twofa.busy" @click="onCancelPending">{{ t('account.twofa_cancel') }}</Btn>
        </form>
      </template>

      <!-- Enabled: recovery codes + disable -->
      <template v-else>
        <div class="mb-4 rounded-lg border border-[var(--ll-border)] p-3">
          <p class="mb-2 text-xs text-[var(--ll-muted)]">{{ t('account.twofa_recovery_codes') }}</p>
          <div v-if="twofa.recovery.length" class="mb-3 grid grid-cols-2 gap-1 font-mono text-[0.8rem]">
            <code v-for="c in twofa.recovery" :key="c">{{ c }}</code>
          </div>
          <div class="flex gap-2">
            <Btn variant="soft" size="sm" icon="key" :loading="twofa.busy" @click="onRegenerate">
              {{ t('account.twofa_regenerate') }}
            </Btn>
            <Btn v-if="twofa.recovery.length" variant="ghost" size="sm" @click="copyRecovery">
              {{ t('account.cli_copy') }}
            </Btn>
          </div>
        </div>
        <Btn variant="ghost" icon="close" class="text-red-600 hover:bg-red-500/10" :loading="twofa.busy" @click="onDisable">
          {{ t('account.twofa_disable') }}
        </Btn>
      </template>
    </Card>

    <!-- Password step-up (enable / disable / regenerate recovery codes) -->
    <Modal :model-value="pwPrompt.show" :title="t('account.password_current')" width="420px" @update:model-value="(v) => { if (!v) cancelPassword(); }">
      <form @submit.prevent="submitPassword">
        <TextField
          v-model="pwPrompt.value"
          :label="t('account.password_current')"
          type="password"
          autocomplete="current-password"
          autofocus
        />
      </form>
      <template #footer>
        <Btn variant="ghost" @click="cancelPassword">{{ t('common.cancel') }}</Btn>
        <Btn variant="solid" :disabled="!pwPrompt.value" @click="submitPassword">{{ t('common.confirm') }}</Btn>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Icon, Badge, TextField, Modal } from '@spa/ui';
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

onMounted(() => {
  // Authoritative confirmed-state comes from /me (auth.user.two_factor). We must
  // NOT probe the enrollment QR endpoint on mount: it is enrollment-only and 404s
  // unless a secret is mid-enrollment, which would surface a spurious console 404
  // on every normal page load. The QR is fetched only after "Enable 2FA"
  // (password step-up → POST /enable → GET /qr) inside onEnable().
  twofa.status = auth.user?.two_factor ? 'on' : 'off';
});

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
    // Refresh /me so a force-2FA enrolment redirect clears immediately.
    await auth.bootstrap();
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
