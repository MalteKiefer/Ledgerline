<template>
  <div class="mx-auto" style="max-width: 960px">
    <!-- Mail (SMTP) -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiEmailOutline" size="small" />
        {{ t('settings.notify_mail_heading') }}
        <v-spacer />
        <v-switch v-model="form.mail_enabled" color="primary" density="compact" hide-details inset />
      </v-card-title>
      <v-divider />
      <v-card-text v-show="form.mail_enabled">
        <v-row dense>
          <v-col cols="12" sm="8">
            <v-text-field v-model="form.smtp_host" :label="t('settings.smtp_host')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field v-model.number="form.smtp_port" :label="t('settings.smtp_port')" type="number" min="1" max="65535" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-select v-model="form.smtp_encryption" :items="encryptionItems" :label="t('settings.smtp_encryption')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="8">
            <v-text-field v-model="form.smtp_username" :label="t('settings.smtp_username')" autocomplete="off" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-text-field
              v-model="form.smtp_password"
              :label="t('settings.smtp_password')"
              type="password"
              autocomplete="new-password"
              :hint="form.has_smtp_password ? t('settings.notify_secret_keep_hint') : undefined"
              :persistent-hint="form.has_smtp_password"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.smtp_from_address" :label="t('settings.smtp_from_address')" type="email" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model="form.smtp_from_name" :label="t('settings.smtp_from_name')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
        </v-row>
        <v-btn class="mt-3" variant="tonal" :prepend-icon="mdiSend" :loading="testing === 'mail'" :disabled="!form.mail_enabled || saving" @click="test('mail')">
          {{ t('settings.notify_test_send', { channel: 'Mail' }) }}
        </v-btn>
      </v-card-text>
    </v-card>

    <!-- NTFY -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiBellOutline" size="small" />
        {{ t('settings.notify_ntfy_heading') }}
        <v-spacer />
        <v-switch v-model="form.ntfy_enabled" color="primary" density="compact" hide-details inset />
      </v-card-title>
      <v-divider />
      <v-card-text v-show="form.ntfy_enabled">
        <v-row dense>
          <v-col cols="12" sm="7">
            <v-text-field v-model="form.ntfy_url" :label="t('settings.ntfy_url')" type="url" placeholder="https://ntfy.sh" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="5">
            <v-text-field v-model="form.ntfy_topic" :label="t('settings.ntfy_topic')" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-text-field
              v-model="form.ntfy_token"
              :label="t('settings.ntfy_token')"
              type="password"
              autocomplete="new-password"
              :hint="form.has_ntfy_token ? t('settings.notify_secret_keep_hint') : undefined"
              :persistent-hint="form.has_ntfy_token"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
        </v-row>
        <v-btn class="mt-3" variant="tonal" :prepend-icon="mdiSend" :loading="testing === 'ntfy'" :disabled="!form.ntfy_enabled || saving" @click="test('ntfy')">
          {{ t('settings.notify_test_send', { channel: 'ntfy' }) }}
        </v-btn>
      </v-card-text>
    </v-card>

    <!-- Webhook -->
    <v-card rounded="xl" border flat class="mb-4">
      <v-card-title class="d-flex align-center ga-2 py-4">
        <v-icon :icon="mdiWebhook" size="small" />
        {{ t('settings.notify_webhook_heading') }}
        <v-spacer />
        <v-switch v-model="form.webhook_enabled" color="primary" density="compact" hide-details inset />
      </v-card-title>
      <v-divider />
      <v-card-text v-show="form.webhook_enabled">
        <v-row dense>
          <v-col cols="12">
            <v-text-field v-model="form.webhook_url" :label="t('settings.webhook_url')" type="url" placeholder="https://…" variant="outlined" density="comfortable" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-text-field
              v-model="form.webhook_secret"
              :label="t('settings.webhook_secret')"
              type="password"
              autocomplete="new-password"
              :hint="form.has_webhook_secret ? t('settings.notify_secret_keep_hint') : undefined"
              :persistent-hint="form.has_webhook_secret"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
          </v-col>
        </v-row>
        <v-btn class="mt-3" variant="tonal" :prepend-icon="mdiSend" :loading="testing === 'webhook'" :disabled="!form.webhook_enabled || saving" @click="test('webhook')">
          {{ t('settings.notify_test_send', { channel: 'Webhook' }) }}
        </v-btn>
      </v-card-text>
    </v-card>

    <!-- Sticky save bar -->
    <v-card rounded="xl" border flat color="surface" style="position: sticky; bottom: 12px; z-index: 2">
      <v-card-actions class="px-4 py-3">
        <v-spacer />
        <v-btn color="primary" variant="flat" :prepend-icon="mdiContentSave" :loading="saving" :disabled="loading" @click="save">
          {{ t('settings.save') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api, ApiError } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { mdiEmailOutline, mdiBellOutline, mdiWebhook, mdiSend, mdiContentSave } from '@mdi/js';

type Channel = 'mail' | 'ntfy' | 'webhook';

/** Shape of GET /api/v1/admin/notifications (secrets are write-only → has_* booleans). */
interface NotificationsResponse {
  mail_enabled: boolean;
  smtp_host: string | null;
  smtp_port: number | null;
  smtp_encryption: string | null;
  smtp_username: string | null;
  smtp_from_address: string | null;
  smtp_from_name: string | null;
  has_smtp_password: boolean;
  ntfy_enabled: boolean;
  ntfy_url: string | null;
  ntfy_topic: string | null;
  has_ntfy_token: boolean;
  webhook_enabled: boolean;
  webhook_url: string | null;
  has_webhook_secret: boolean;
}

interface FormState {
  mail_enabled: boolean;
  smtp_host: string;
  smtp_port: number | null;
  smtp_encryption: string;
  smtp_username: string;
  smtp_password: string;
  smtp_from_address: string;
  smtp_from_name: string;
  has_smtp_password: boolean;
  ntfy_enabled: boolean;
  ntfy_url: string;
  ntfy_topic: string;
  ntfy_token: string;
  has_ntfy_token: boolean;
  webhook_enabled: boolean;
  webhook_url: string;
  webhook_secret: string;
  has_webhook_secret: boolean;
}

const { success, error } = useToast();

const encryptionItems = ['tls', 'ssl', 'none'];

const form = reactive<FormState>({
  mail_enabled: false,
  smtp_host: '',
  smtp_port: null,
  smtp_encryption: 'tls',
  smtp_username: '',
  smtp_password: '',
  smtp_from_address: '',
  smtp_from_name: '',
  has_smtp_password: false,
  ntfy_enabled: false,
  ntfy_url: '',
  ntfy_topic: '',
  ntfy_token: '',
  has_ntfy_token: false,
  webhook_enabled: false,
  webhook_url: '',
  webhook_secret: '',
  has_webhook_secret: false,
});

const loading = ref(true);
const saving = ref(false);
const testing = ref<Channel | null>(null);

function apply(c: NotificationsResponse) {
  form.mail_enabled = !!c.mail_enabled;
  form.smtp_host = c.smtp_host ?? '';
  form.smtp_port = c.smtp_port ?? null;
  form.smtp_encryption = c.smtp_encryption ?? 'tls';
  form.smtp_username = c.smtp_username ?? '';
  form.smtp_from_address = c.smtp_from_address ?? '';
  form.smtp_from_name = c.smtp_from_name ?? '';
  form.has_smtp_password = !!c.has_smtp_password;
  form.ntfy_enabled = !!c.ntfy_enabled;
  form.ntfy_url = c.ntfy_url ?? '';
  form.ntfy_topic = c.ntfy_topic ?? '';
  form.has_ntfy_token = !!c.has_ntfy_token;
  form.webhook_enabled = !!c.webhook_enabled;
  form.webhook_url = c.webhook_url ?? '';
  form.has_webhook_secret = !!c.has_webhook_secret;
  // Reset write-only secret inputs after a (re)load.
  form.smtp_password = '';
  form.ntfy_token = '';
  form.webhook_secret = '';
}

/** Payload for PUT — blank secrets are omitted so the stored value is kept. */
function payload(): Record<string, unknown> {
  const body: Record<string, unknown> = {
    mail_enabled: form.mail_enabled,
    smtp_host: form.smtp_host,
    smtp_port: form.smtp_port,
    smtp_encryption: form.smtp_encryption,
    smtp_username: form.smtp_username,
    smtp_from_address: form.smtp_from_address,
    smtp_from_name: form.smtp_from_name,
    ntfy_enabled: form.ntfy_enabled,
    ntfy_url: form.ntfy_url,
    ntfy_topic: form.ntfy_topic,
    webhook_enabled: form.webhook_enabled,
    webhook_url: form.webhook_url,
  };
  if (form.smtp_password) body.smtp_password = form.smtp_password;
  if (form.ntfy_token) body.ntfy_token = form.ntfy_token;
  if (form.webhook_secret) body.webhook_secret = form.webhook_secret;
  return body;
}

async function save() {
  saving.value = true;
  try {
    const res = await api.put<NotificationsResponse>('/api/v1/admin/notifications', payload());
    apply(res);
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  } finally {
    saving.value = false;
  }
}

async function test(channel: Channel) {
  testing.value = channel;
  try {
    const res = await api.post<{ ok: boolean; detail?: string }>('/api/v1/admin/notifications/test', { channel });
    if (res.ok) success(t('common.saved'));
    else error(res.detail || t('common.error'));
  } catch (e) {
    const detail = e instanceof ApiError && e.body && typeof e.body === 'object' ? (e.body as { detail?: string }).detail : undefined;
    error(detail || t('common.error'));
  } finally {
    testing.value = null;
  }
}

onMounted(async () => {
  try {
    apply(await api.get<NotificationsResponse>('/api/v1/admin/notifications'));
  } catch {
    error(t('common.error'));
  } finally {
    loading.value = false;
  }
});
</script>
