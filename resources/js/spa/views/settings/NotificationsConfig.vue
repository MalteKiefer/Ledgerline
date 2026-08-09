<template>
  <div class="mx-auto max-w-3xl">
    <!-- Mail (SMTP) -->
    <Card class="mb-4">
      <template #header>
        <Icon name="notifications" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.notify_mail_heading') }}</h2>
      </template>
      <template #actions>
        <label class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
          <input v-model="form.mail_enabled" type="checkbox" class="peer sr-only">
          <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
        </label>
      </template>
      <div v-show="form.mail_enabled" class="space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <TextField class="sm:col-span-2" v-model="form.smtp_host" :label="t('settings.smtp_host')" />
          <TextField v-model="form.smtp_port" :label="t('settings.smtp_port')" type="number" />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Select v-model="form.smtp_encryption" :label="t('settings.smtp_encryption')" :options="encryptionItems" />
          <TextField class="sm:col-span-2" v-model="form.smtp_username" :label="t('settings.smtp_username')" autocomplete="off" />
        </div>
        <TextField
          v-model="form.smtp_password" :label="t('settings.smtp_password')" type="password" autocomplete="new-password"
          :hint="form.has_smtp_password ? t('settings.notify_secret_keep_hint') : undefined"
        />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField v-model="form.smtp_from_address" :label="t('settings.smtp_from_address')" type="email" />
          <TextField v-model="form.smtp_from_name" :label="t('settings.smtp_from_name')" />
        </div>
        <Btn variant="soft" :loading="testing === 'mail'" :disabled="!form.mail_enabled || saving" @click="test('mail')">
          {{ t('settings.notify_test_send', { channel: 'Mail' }) }}
        </Btn>
      </div>
    </Card>

    <!-- NTFY -->
    <Card class="mb-4">
      <template #header>
        <Icon name="notifications" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.notify_ntfy_heading') }}</h2>
      </template>
      <template #actions>
        <label class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
          <input v-model="form.ntfy_enabled" type="checkbox" class="peer sr-only">
          <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
        </label>
      </template>
      <div v-show="form.ntfy_enabled" class="space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <TextField class="sm:col-span-2" v-model="form.ntfy_url" :label="t('settings.ntfy_url')" type="url" placeholder="https://ntfy.sh" />
          <TextField v-model="form.ntfy_topic" :label="t('settings.ntfy_topic')" />
        </div>
        <TextField
          v-model="form.ntfy_token" :label="t('settings.ntfy_token')" type="password" autocomplete="new-password"
          :hint="form.has_ntfy_token ? t('settings.notify_secret_keep_hint') : undefined"
        />
        <Btn variant="soft" :loading="testing === 'ntfy'" :disabled="!form.ntfy_enabled || saving" @click="test('ntfy')">
          {{ t('settings.notify_test_send', { channel: 'ntfy' }) }}
        </Btn>
      </div>
    </Card>

    <!-- Webhook -->
    <Card class="mb-4">
      <template #header>
        <Icon name="notifications" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.notify_webhook_heading') }}</h2>
      </template>
      <template #actions>
        <label class="relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center">
          <input v-model="form.webhook_enabled" type="checkbox" class="peer sr-only">
          <span class="pointer-events-none absolute inset-0 rounded-full bg-black/10 transition-colors peer-checked:bg-primary-500 dark:bg-white/15" />
          <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
        </label>
      </template>
      <div v-show="form.webhook_enabled" class="space-y-4">
        <TextField v-model="form.webhook_url" :label="t('settings.webhook_url')" type="url" placeholder="https://…" />
        <TextField
          v-model="form.webhook_secret" :label="t('settings.webhook_secret')" type="password" autocomplete="new-password"
          :hint="form.has_webhook_secret ? t('settings.notify_secret_keep_hint') : undefined"
        />
        <Btn variant="soft" :loading="testing === 'webhook'" :disabled="!form.webhook_enabled || saving" @click="test('webhook')">
          {{ t('settings.notify_test_send', { channel: 'Webhook' }) }}
        </Btn>
      </div>
    </Card>

    <!-- Sticky save bar -->
    <div class="sticky bottom-3 z-10 flex justify-end rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 shadow-sm">
      <Btn variant="solid" :loading="saving" :disabled="loading" @click="save">
        {{ t('settings.save') }}
      </Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api, ApiError } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField, Select } from '@spa/ui';

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

const encryptionItems = ['tls', 'ssl', 'none'].map((v) => ({ title: v, value: v }));

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
