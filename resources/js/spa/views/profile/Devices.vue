<template>
  <div>
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

    <!-- Connected devices -->
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

    <!-- WebDAV access -->
    <v-card rounded="xl" border flat>
      <v-card-title>{{ t('account.webdav_title') }}</v-card-title>
      <v-card-text>
        <p class="text-medium-emphasis mb-4">{{ t('account.webdav_desc') }}</p>
        <template v-if="webdav.enabled">
          <v-text-field :model-value="webdav.url" :label="t('account.webdav_url')" readonly variant="outlined" density="comfortable" hide-details class="mb-3" />
          <v-text-field :model-value="webdav.username" :label="t('account.webdav_user')" readonly variant="outlined" density="comfortable" hide-details class="mb-3" />
        </template>
        <v-form @submit.prevent="onSaveWebdav">
          <v-text-field
            v-model="webdavPassword"
            :label="t('account.webdav_password')"
            type="password"
            autocomplete="new-password"
            variant="outlined"
            density="comfortable"
            :error-messages="webdavErr"
          />
          <div class="d-flex ga-2 flex-wrap">
            <v-btn type="submit" color="primary" :loading="webdavBusy">{{ t('account.webdav_save') }}</v-btn>
            <v-btn v-if="webdav.enabled" variant="text" color="error" :loading="webdavBusy" @click="onClearWebdav">{{ t('account.webdav_disable') }}</v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { mdiCellphoneRemove, mdiDelete, mdiQrcode } from '@mdi/js';
import { useProfileStore, type DeviceToken, type WebDavAccess } from '@spa/stores/profile';
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
  await Promise.all([p.loadDevices(), loadWebdav()]);
});

onUnmounted(() => clearTimeout(pollTimer));

function deviceSub(d: DeviceToken): string {
  return [d.meta, d.version].filter(Boolean).join(' · ');
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
