<template>
  <div>
    <Card class="mb-4">
      <template #header>
        <Icon name="security" :size="18" class="text-[var(--ll-muted)]" />
        <h2 class="text-sm font-semibold">{{ t('settings.security_devices_heading') }}</h2>
      </template>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <TextField
          v-model="form.max_connected_devices"
          :label="t('settings.security_max_devices')"
          type="number"
        />
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
import { api } from '@spa/api/client';
import { useToast } from '@spa/composables/useToast';
import { Icon, Btn, Card, TextField } from '@spa/ui';

interface SecurityResponse {
  max_connected_devices: number;
}

const { success, error } = useToast();

const form = reactive<{ max_connected_devices: number | null }>({ max_connected_devices: null });

const loading = ref(true);
const saving = ref(false);

async function save() {
  saving.value = true;
  try {
    const res = await api.put<SecurityResponse>('/api/v1/admin/security', {
      max_connected_devices: form.max_connected_devices,
    });
    form.max_connected_devices = res.max_connected_devices;
    success(t('common.saved'));
  } catch {
    error(t('common.error'));
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await api.get<SecurityResponse>('/api/v1/admin/security');
    form.max_connected_devices = res.max_connected_devices;
  } catch {
    error(t('common.error'));
  } finally {
    loading.value = false;
  }
});
</script>
