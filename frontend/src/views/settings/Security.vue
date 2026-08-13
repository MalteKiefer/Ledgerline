<template>
  <div>
    <Card class="mb-4">
      <template #header>
        <Icon name="security" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.security_devices_heading') }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField v-model="form.max_connected_devices" :label="t('settings.security_max_devices')" type="number" />
      </div>
    </Card>

    <!-- Password policy -->
    <Card class="mb-4">
      <template #header>
        <Icon name="password" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.pw_policy') }}</h2>
      </template>
      <div class="space-y-3">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField v-model="form.pw_min_length" :label="t('settings.pw_min_length')" type="number" :placeholder="'12'" :hint="t('settings.pw_min_hint')" />
        </div>
        <label class="flex items-center justify-between gap-3 text-sm"><span class="font-medium">{{ t('settings.pw_mixed_case') }}</span><input v-model="form.pw_require_mixed_case" type="checkbox" class="h-5 w-5 accent-primary-500"></label>
        <label class="flex items-center justify-between gap-3 text-sm"><span class="font-medium">{{ t('settings.pw_numbers') }}</span><input v-model="form.pw_require_numbers" type="checkbox" class="h-5 w-5 accent-primary-500"></label>
        <label class="flex items-center justify-between gap-3 text-sm"><span class="font-medium">{{ t('settings.pw_symbols') }}</span><input v-model="form.pw_require_symbols" type="checkbox" class="h-5 w-5 accent-primary-500"></label>
        <label class="flex items-center justify-between gap-3 text-sm"><span><span class="font-medium">{{ t('settings.pw_breaches') }}</span><span class="block text-xs text-[var(--ll-muted)]">{{ t('settings.pw_breaches_hint') }}</span></span><input v-model="form.pw_check_breaches" type="checkbox" class="h-5 w-5 accent-primary-500"></label>
      </div>
    </Card>

    <!-- Two-factor policy -->
    <Card class="mb-4">
      <template #header>
        <Icon name="phonelink_lock" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.tfa_policy') }}</h2>
      </template>
      <label class="flex items-center justify-between gap-3 text-sm"><span><span class="font-medium">{{ t('settings.force_2fa') }}</span><span class="block text-xs text-[var(--ll-muted)]">{{ t('settings.force_2fa_hint') }}</span></span><input v-model="form.force_2fa" type="checkbox" class="h-5 w-5 accent-primary-500"></label>
    </Card>

    <div class="sticky bottom-3 z-10 flex justify-end rounded-xl border border-[var(--ll-border)] bg-[var(--ll-surface)] px-4 py-3 shadow-sm">
      <Btn variant="solid" :loading="saving" :disabled="loading" @click="save">{{ t('settings.save') }}</Btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { trans as t } from 'laravel-vue-i18n';
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField } from '@spa/ui';

interface SecurityResponse {
  max_connected_devices: number;
  pw_min_length: number | null;
  pw_require_mixed_case: boolean;
  pw_require_numbers: boolean;
  pw_require_symbols: boolean;
  pw_check_breaches: boolean;
  force_2fa: boolean;
}

const { success, error } = useToast();
const form = reactive<SecurityResponse & { max_connected_devices: number | null }>({
  max_connected_devices: null as unknown as number, pw_min_length: null,
  pw_require_mixed_case: false, pw_require_numbers: false, pw_require_symbols: false, pw_check_breaches: false, force_2fa: false,
});
const loading = ref(true);
const saving = ref(false);

function apply(r: SecurityResponse) {
  form.max_connected_devices = r.max_connected_devices;
  form.pw_min_length = r.pw_min_length;
  form.pw_require_mixed_case = r.pw_require_mixed_case;
  form.pw_require_numbers = r.pw_require_numbers;
  form.pw_require_symbols = r.pw_require_symbols;
  form.pw_check_breaches = r.pw_check_breaches;
  form.force_2fa = r.force_2fa;
}

async function save() {
  saving.value = true;
  try {
    apply(await api.put<SecurityResponse>('/api/v1/admin/security', {
      max_connected_devices: form.max_connected_devices,
      pw_min_length: form.pw_min_length === null || String(form.pw_min_length) === '' ? null : Number(form.pw_min_length),
      pw_require_mixed_case: form.pw_require_mixed_case,
      pw_require_numbers: form.pw_require_numbers,
      pw_require_symbols: form.pw_require_symbols,
      pw_check_breaches: form.pw_check_breaches,
      force_2fa: form.force_2fa,
    }));
    success(t('common.saved'));
  } catch { error(t('common.error')); } finally { saving.value = false; }
}

onMounted(async () => {
  try { apply(await api.get<SecurityResponse>('/api/v1/admin/security')); } catch { error(t('common.error')); } finally { loading.value = false; }
});
</script>
