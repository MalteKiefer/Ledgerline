<template>
  <div>
    <!-- Mobile app: QR device pairing -->
    <Card :title="t('account.devices_heading')" class="mb-4">
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('account.devices_hint') }}</p>

      <Btn v-if="!pairing.active" variant="soft" icon="smartphone" :loading="pairing.busy" @click="onStartPairing">
        {{ t('account.devices_connect') }}
      </Btn>

      <template v-else>
        <div v-if="pairing.status === 'pending_scan' || pairing.status === 'pending_approval'" class="flex flex-col items-start gap-4 sm:flex-row">
          <img v-if="pairing.qr" :src="pairing.qr" class="h-40 w-40 shrink-0 rounded-lg border border-[var(--ll-border)] bg-white object-cover p-1" >
          <div>
            <p v-if="pairing.status === 'pending_scan'" class="text-sm text-[var(--ll-muted)]">{{ t('account.devices_scan_hint') }}</p>
            <div v-else>
              <p class="mb-2 text-sm">{{ t('account.devices_approve_q') }} “{{ pairing.deviceName }}”?</p>
              <div class="flex gap-2">
                <Btn variant="solid" :loading="pairing.busy" @click="onApprovePairing">{{ t('account.devices_allow') }}</Btn>
                <Btn variant="soft" :disabled="pairing.busy" @click="onRejectPairing">{{ t('account.devices_deny') }}</Btn>
              </div>
            </div>
          </div>
        </div>
        <div v-else>
          <p v-if="pairing.status === 'approved' || pairing.status === 'consumed'" class="text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ t('account.devices_connected') }}</p>
          <p v-else-if="pairing.status === 'rejected'" class="text-sm text-[var(--ll-muted)]">{{ t('account.devices_rejected') }}</p>
          <p v-else class="text-sm text-[var(--ll-muted)]">{{ t('account.devices_expired') }}</p>
          <Btn variant="ghost" size="sm" class="mt-2" @click="resetPairing">{{ t('account.devices_again') }}</Btn>
        </div>
      </template>
    </Card>

    <!-- Connected devices -->
    <Card :title="t('account.devices_list_heading')" body-class="p-0" class="mb-4">
      <div v-for="d in p.devices" :key="d.id" class="flex items-center gap-3 border-t border-[var(--ll-border)] px-5 py-3 first:border-t-0">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg" :class="d.current ? 'bg-primary-500/15 text-primary-600 dark:text-primary-300' : 'bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10'">
          <Icon name="smartphone" :size="18" />
        </span>
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium">{{ d.name }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ deviceSub(d) }}</div>
        </div>
        <Badge v-if="d.current" tone="primary">{{ t('account.sessions_current') }}</Badge>
        <Badge v-if="d.wipeRequested" tone="warning">{{ t('account.devices_wipe_pending') }}</Badge>
        <button class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[var(--ll-muted)] hover:bg-black/[0.05] dark:hover:bg-white/10" :title="t('account.devices_wipe_pending')" @click="p.wipeDevice(d.id)">
          <Icon name="close" :size="18" />
        </button>
        <button class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('common.delete')" @click="p.revokeDevice(d.id)">
          <Icon name="delete" :size="18" />
        </button>
      </div>
      <div v-if="!p.devices.length" class="border-t border-[var(--ll-border)] px-5 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('common.none') }}</div>
    </Card>

    <!-- Active web sessions -->
    <Card :title="t('account.sessions_heading')" body-class="p-0" class="mb-4">
      <p class="px-5 py-3 text-sm text-[var(--ll-muted)]">{{ t('account.sessions_hint') }}</p>
      <div v-for="s in p.sessions" :key="s.id" class="flex items-center gap-3 border-t border-[var(--ll-border)] px-5 py-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-black/[0.05] text-[var(--ll-muted)] dark:bg-white/10">
          <Icon name="public" :size="18" />
        </span>
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium">{{ s.user_agent || t('account.sessions_unknown') }}</div>
          <div class="truncate text-xs text-[var(--ll-muted)]">{{ sessionSub(s) }}</div>
        </div>
        <Badge v-if="s.current" tone="primary">{{ t('account.sessions_current') }}</Badge>
        <button v-else class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-red-600 hover:bg-red-500/10" :title="t('common.delete')" @click="onRevokeSession(s.id)">
          <Icon name="logout" :size="18" />
        </button>
      </div>
      <div v-if="!p.sessions.length" class="border-t border-[var(--ll-border)] px-5 py-6 text-center text-sm text-[var(--ll-muted)]">{{ t('account.sessions_none') }}</div>
    </Card>

    <!-- WebDAV access -->
    <Card :title="t('account.webdav_title')">
      <p class="mb-4 text-sm text-[var(--ll-muted)]">{{ t('account.webdav_desc') }}</p>
      <template v-if="webdav.enabled">
        <TextField class="mb-3" :model-value="webdav.url" :label="t('account.webdav_url')" disabled />
        <TextField class="mb-3" :model-value="webdav.username" :label="t('account.webdav_user')" disabled />
      </template>
      <form @submit.prevent="onSaveWebdav">
        <TextField
          v-model="webdavPassword"
          class="mb-3"
          :label="t('account.webdav_password')"
          type="password"
          autocomplete="new-password"
          :error="webdavErr && webdavErr.length ? webdavErr[0] : ''"
        />
        <div class="flex flex-wrap gap-2">
          <Btn type="submit" variant="solid" :loading="webdavBusy">{{ t('account.webdav_save') }}</Btn>
          <Btn v-if="webdav.enabled" variant="ghost" class="text-red-600 hover:bg-red-500/10" :loading="webdavBusy" @click="onClearWebdav">{{ t('account.webdav_disable') }}</Btn>
        </div>
      </form>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { Card, Btn, Icon, Badge, TextField } from '@spa/ui';
import { useProfileStore, type DeviceToken, type Session, type WebDavAccess } from '@spa/stores/profile';
import { useToast } from '@spa/composables/useToast';
import { ApiError } from '@spa/api/client';

const p = useProfileStore();
const { success, error } = useToast();

// --- Device pairing --------------------------------------------------------
const pairing = reactive<{ active: boolean; id: number; qr: string; status: string; deviceName: string | null; busy: boolean }>(
  { active: false, id: 0, qr: '', status: '', deviceName: null, busy: false },
);
let pollTimer: ReturnType<typeof setTimeout> | undefined;

// --- WebDAV ----------------------------------------------------------------
const webdav = reactive<WebDavAccess>({ enabled: false, username: '', url: '' });
const webdavPassword = ref('');
const webdavErr = ref<string[] | undefined>(undefined);
const webdavBusy = ref(false);

onMounted(async () => {
  await Promise.all([p.loadDevices(), p.loadSessions(), loadWebdav()]);
});

onUnmounted(() => clearTimeout(pollTimer));

function deviceSub(d: DeviceToken): string {
  return [d.meta, d.version].filter(Boolean).join(' · ');
}

function sessionSub(s: Session): string {
  return [s.ip, s.last_active].filter(Boolean).join(' · ');
}

async function onRevokeSession(id: string) {
  try {
    await p.revokeSession(id);
    success(t('account.session_revoked'));
  } catch {
    error(t('common.error'));
  }
}

async function loadWebdav() {
  try {
    const w = await p.getWebdav();
    Object.assign(webdav, w);
  } catch { /* non-fatal */ }
}

async function onSaveWebdav() {
  webdavBusy.value = true;
  webdavErr.value = undefined;
  try {
    const w = await p.setWebdav(webdavPassword.value);
    Object.assign(webdav, w);
    webdavPassword.value = '';
    success(t('account.webdav_set'));
  } catch (e) {
    if (e instanceof ApiError && e.fields?.webdav_password?.length) webdavErr.value = e.fields.webdav_password;
    else error(t('common.error'));
  } finally {
    webdavBusy.value = false;
  }
}

async function onClearWebdav() {
  webdavBusy.value = true;
  try {
    await p.clearWebdav();
    Object.assign(webdav, { enabled: false, username: '', url: '' });
    webdavPassword.value = '';
    success(t('account.webdav_cleared'));
  } catch {
    error(t('common.error'));
  } finally {
    webdavBusy.value = false;
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
</script>
